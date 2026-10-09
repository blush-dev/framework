<?php

/**
 * Built-in roles.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

use Blush\Extension\ExtensionKind;
use Blush\Media\MediaKind;

/**
 * The framework's roles (D-217). `config/auth.php` can redefine any of
 * them but the owner and the member by name, or add others; the admin can
 * change the capabilities of all but those two (D-312, D-500).
 *
 * The owner (D-500) always has every capability, and only an owner
 * changes an owner's account or gives the role (`PeopleRules`), so
 * whoever runs the site can hand out administrator and never be locked
 * out by it. The administrator has a list: every built-in capability but
 * installing, updating, and deleting plugins and themes, which put code
 * on the site (`ExtensionAction::changesCode()`), and Site Health
 * (`site.health`, D-544). An owner may add them,
 * or a plugin's capabilities, to it.
 *
 * The member (D-365) never has a capability: it's what an account holds
 * when it holds nothing else (`Accounts::settle()`), so making an
 * account without being allowed to give roles hands out nothing.
 */
enum BuiltInRole: string
{
	case Owner         = 'owner';
	case Administrator = 'administrator';
	case Editor        = 'editor';
	case Author        = 'author';
	case Contributor   = 'contributor';
	case Member        = 'member';

	/**
	 * Whether the admin never changes the role: the owner always has
	 * everything, and the member nothing (D-500, D-365). Config can't
	 * redefine them either.
	 */
	public function isFixed(): bool
	{
		return $this === self::Owner || $this === self::Member;
	}

	/**
	 * Returns the role.
	 */
	public function role(): Role
	{
		return new Role($this->value, $this->label(), $this->capabilities(), $this->description());
	}

	/**
	 * Returns what the role is for, in a line.
	 */
	public function description(): string
	{
		return match ($this) {
			self::Owner         => 'Everything, always. Only an owner can change an owner\'s account or make another owner.',
			self::Administrator => 'Runs the site: accounts, roles, content types, settings, and every entry. Installs icon packs, but not plugins or themes, and doesn\'t see Site Health.',
			self::Editor        => 'Publishes and edits anyone\'s entries, and publishes the site. Can\'t change its structure.',
			self::Author        => 'Writes and publishes their own entries, and uploads media.',
			self::Contributor   => 'Writes drafts of their own entries, and uploads images. Can\'t publish.',
			self::Member        => 'Signs in and looks after their own account. Nothing else.'
		};
	}

	/**
	 * Returns the role's label.
	 */
	public function label(): string
	{
		return ucfirst($this->value);
	}

	/**
	 * Returns the role's capabilities. Content capabilities are for every
	 * type (`content.*.edit`, D-359), so they cover types added later. Only
	 * the owner's `*` covers capabilities a plugin adds.
	 *
	 * @return list<string>
	 */
	public function capabilities(): array
	{
		$content = match ($this) {
			self::Administrator, self::Editor => ContentAction::cases(),
			self::Author                      => [ContentAction::Create, ContentAction::Edit, ContentAction::Publish, ContentAction::Delete],
			self::Contributor                 => [ContentAction::Create, ContentAction::Edit, ContentAction::Delete],
			default                           => []
		};

		$site = match ($this) {
			// Site Health is the owner's (D-544).
			self::Administrator => array_values(array_filter(Capability::cases(), static fn (Capability $capability): bool => $capability !== Capability::SiteHealth)),
			self::Editor => [
				Capability::MediaEdit,
				Capability::MediaEditOthers,
				Capability::MediaDelete,
				Capability::MediaDeleteOthers,
				Capability::HtmlAllowed,
				Capability::MenusEdit,
				Capability::SitePublish,
				Capability::CacheClear
			],
			self::Author      => [Capability::MediaEdit, Capability::MediaDelete],
			self::Contributor => [Capability::MediaEdit],
			default           => []
		};

		// Uploading is by kind (D-407).
		$uploads = match ($this) {
			self::Administrator, self::Editor, self::Author => [MediaKind::UPLOAD_EVERY],
			self::Contributor                               => [MediaKind::Image->uploadCapability()],
			default                                         => []
		};

		// Every extension action but those that change code (D-500).
		$extensions = $this !== self::Administrator ? [] : array_merge(...array_map(
			static fn (ExtensionKind $kind): array => array_values(array_map(
				static fn (ExtensionAction $action): string => $action->on($kind),
				array_filter(ExtensionAction::cases(), static fn (ExtensionAction $action): bool => ! $action->changesCode($kind))
			)),
			ExtensionAction::kinds()
		));

		return $this === self::Owner ? [Role::ALL] : [
			...array_map(static fn (ContentAction $action): string => $action->on(ContentAction::EVERY), $content),
			...$uploads,
			...array_map(static fn (Capability $capability): string => $capability->value, $site),
			...$extensions
		];
	}
}
