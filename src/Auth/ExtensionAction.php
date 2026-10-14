<?php

/**
 * Extension actions.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

use Blush\Extension\ExtensionKind;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * What a role may do with each kind of extension (D-389). Each kind has a
 * capability for each action, named `extensions.{kind}.{action}`
 * (`extensions.plugins.install`, `extensions.icon-packs.activate`), and
 * `extensions.*.{action}` grants it on every kind. Every action but
 * seeing also needs seeing (`Permissions`), as the `accounts.*`
 * capabilities need `accounts.view` (D-362).
 */
enum ExtensionAction: string
{
	case View     = 'view';
	case Install  = 'install';
	case Update   = 'update';
	case Activate = 'activate';
	case Delete   = 'delete';

	/**
	 * The kinds, in the order the admin shows them.
	 *
	 * @return list<ExtensionKind>
	 */
	public static function kinds(): array
	{
		return [ExtensionKind::Theme, ExtensionKind::Plugin, ExtensionKind::IconPack, ExtensionKind::AdminTheme];
	}

	/**
	 * Whether the action changes the code a site runs on a kind:
	 * installing, updating, or deleting a plugin or theme (D-500). The
	 * administrator role leaves these out, since whoever holds them can
	 * do anything an owner can. Icon packs and admin themes have no code.
	 */
	public function changesCode(ExtensionKind $kind): bool
	{
		return $kind->runsCode() && ($this === self::Install || $this === self::Update || $this === self::Delete);
	}

	/**
	 * Returns the action's capability on a kind.
	 */
	public function on(ExtensionKind $kind): string
	{
		return sprintf('extensions.%s.%s', self::segment($kind), $this->value);
	}

	/**
	 * Returns the action's label on a kind.
	 */
	public function label(ExtensionKind $kind, Messages $messages): Text
	{
		$name = match ($kind) {
			ExtensionKind::Plugin     => 'plugin',
			ExtensionKind::Theme      => 'theme',
			ExtensionKind::IconPack   => 'icon_pack',
			ExtensionKind::AdminTheme => 'admin_theme'
		};

		return match ($this) {
			self::View     => $messages->t('auth.extension_action.view', 'See {kind, select, plugin {plugins} theme {themes} icon_pack {icon packs} other {admin themes}}', kind: $name),
			self::Install  => $messages->t('auth.extension_action.install', 'Install {kind, select, plugin {plugins} theme {themes} icon_pack {icon packs} other {admin themes}}', kind: $name),
			self::Update   => $messages->t('auth.extension_action.update', 'Update {kind, select, plugin {plugins} theme {themes} icon_pack {icon packs} other {admin themes}}', kind: $name),
			self::Activate => match ($kind) {
				ExtensionKind::Theme      => $messages->t('auth.extension_action.activate_themes', 'Activate themes'),
				ExtensionKind::AdminTheme => $messages->t('auth.extension_action.activate_admin_themes', 'Turn admin themes on and off, and choose the site\'s default'),
				default                   => $messages->t('auth.extension_action.activate', 'Turn {kind, select, plugin {plugins} theme {themes} icon_pack {icon packs} other {admin themes}} on and off', kind: $name)
			},
			self::Delete   => $messages->t('auth.extension_action.delete', 'Delete {kind, select, plugin {plugins} theme {themes} icon_pack {icon packs} other {admin themes}}', kind: $name)
		};
	}

	/**
	 * Returns the group a kind's capabilities are shown in.
	 */
	public static function group(ExtensionKind $kind, Messages $messages): Text
	{
		return match ($kind) {
			ExtensionKind::Plugin     => $messages->t('auth.extension_action.group.plugin', 'Plugins'),
			ExtensionKind::Theme      => $messages->t('auth.extension_action.group.theme', 'Themes'),
			ExtensionKind::IconPack   => $messages->t('auth.extension_action.group.icon_pack', 'Icon Packs'),
			ExtensionKind::AdminTheme => $messages->t('auth.extension_action.group.admin_theme', 'Admin Themes')
		};
	}

	/**
	 * Splits an extension capability into its kind and action, or returns
	 * `null` for any other capability (and for `*` kinds, which only a
	 * role grants).
	 *
	 * @return ?array{ExtensionKind, self}
	 */
	public static function parse(string $capability): ?array
	{
		if (preg_match('/^extensions\.([a-z-]+)\.([a-z]+)$/', $capability, $match) !== 1) {
			return null;
		}

		$kind   = array_find(self::kinds(), static fn (ExtensionKind $kind): bool => self::segment($kind) === $match[1]);
		$action = self::tryFrom($match[2]);

		return $kind === null || $action === null ? null : [$kind, $action];
	}

	/**
	 * Returns a kind's word in its capabilities.
	 */
	private static function segment(ExtensionKind $kind): string
	{
		return match ($kind) {
			ExtensionKind::Plugin     => 'plugins',
			ExtensionKind::Theme      => 'themes',
			ExtensionKind::IconPack   => 'icon-packs',
			ExtensionKind::AdminTheme => 'admin-themes'
		};
	}
}
