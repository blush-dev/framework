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
		return [ExtensionKind::Theme, ExtensionKind::Plugin, ExtensionKind::IconPack];
	}

	/**
	 * Whether the action changes the code a site runs on a kind:
	 * installing, updating, or deleting a plugin or theme (D-500). The
	 * administrator role leaves these out, since whoever holds them can
	 * do anything an owner can. Icon packs have no code.
	 */
	public function changesCode(ExtensionKind $kind): bool
	{
		return $kind !== ExtensionKind::IconPack && ($this === self::Install || $this === self::Update || $this === self::Delete);
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
	public function label(ExtensionKind $kind): string
	{
		$plural = strtolower(self::group($kind));

		return match ($this) {
			self::View     => "See {$plural}",
			self::Install  => "Install {$plural}",
			self::Update   => "Update {$plural}",
			self::Activate => $kind === ExtensionKind::Theme ? 'Activate themes' : "Turn {$plural} on and off",
			self::Delete   => "Delete {$plural}"
		};
	}

	/**
	 * Returns the group a kind's capabilities are shown in.
	 */
	public static function group(ExtensionKind $kind): string
	{
		return match ($kind) {
			ExtensionKind::Plugin   => 'Plugins',
			ExtensionKind::Theme    => 'Themes',
			ExtensionKind::IconPack => 'Icon Packs'
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
			ExtensionKind::Plugin   => 'plugins',
			ExtensionKind::Theme    => 'themes',
			ExtensionKind::IconPack => 'icon-packs'
		};
	}
}
