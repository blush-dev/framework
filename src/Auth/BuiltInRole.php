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

/**
 * The framework's roles (D-217). `config/auth.php` can redefine any of
 * them but the member by name, or add others; the admin can change the
 * capabilities of all but the administrator and the member (D-312).
 *
 * The member (D-365) never has a capability: it's what an account holds
 * when it holds nothing else (`Accounts::settle()`), so making an
 * account without being allowed to give roles hands out nothing.
 */
enum BuiltInRole: string
{
	case Administrator = 'administrator';
	case Editor        = 'editor';
	case Author        = 'author';
	case Contributor   = 'contributor';
	case Member        = 'member';

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
			self::Administrator => 'Everything, including accounts, roles, content types, and settings.',
			self::Editor        => 'Publishes and edits anyone\'s entries, and publishes the site. Can\'t change its structure.',
			self::Author        => 'Writes and publishes their own entries, and uploads media.',
			self::Contributor   => 'Writes drafts of their own entries. Can\'t publish.',
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
	 * type (`content.*.edit`, D-359), so they cover types added later.
	 *
	 * @return list<string>
	 */
	public function capabilities(): array
	{
		$content = match ($this) {
			self::Administrator => [],
			self::Editor        => ContentAction::cases(),
			self::Author        => [ContentAction::Create, ContentAction::Edit, ContentAction::Publish, ContentAction::Delete],
			self::Contributor   => [ContentAction::Create, ContentAction::Edit, ContentAction::Delete],
			self::Member        => []
		};

		$site = match ($this) {
			self::Editor => [
				Capability::MediaUpload,
				Capability::MediaDelete,
				Capability::MenusEdit,
				Capability::RegionsEdit,
				Capability::SitePublish,
				Capability::CacheClear
			],
			self::Author => [Capability::MediaUpload],
			default      => []
		};

		return $this === self::Administrator ? [Role::ALL] : [
			...array_map(static fn (ContentAction $action): string => $action->on(ContentAction::EVERY), $content),
			...array_map(static fn (Capability $capability): string => $capability->value, $site)
		];
	}
}
