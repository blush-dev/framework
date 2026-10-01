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
 * them by name, or add others; the admin can change the capabilities of
 * all but the administrator (D-312).
 */
enum BuiltInRole: string
{
	case Administrator = 'administrator';
	case Editor        = 'editor';
	case Author        = 'author';
	case Contributor   = 'contributor';

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
			self::Contributor   => 'Writes drafts of their own entries. Can\'t publish.'
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
	 * Returns the role's capabilities.
	 *
	 * @return list<string>
	 */
	public function capabilities(): array
	{
		$own = [
			Capability::ContentCreate,
			Capability::ContentEdit,
			Capability::ContentPublish,
			Capability::ContentDelete,
			Capability::MediaUpload
		];

		$capabilities = match ($this) {
			self::Administrator => [],
			self::Editor        => [
				...$own,
				Capability::ContentEditOthers,
				Capability::ContentPublishOthers,
				Capability::ContentDeleteOthers,
				Capability::MediaDelete,
				Capability::MenusEdit,
				Capability::RegionsEdit,
				Capability::SitePublish,
				Capability::CacheClear
			],
			self::Author      => $own,
			self::Contributor => [Capability::ContentCreate, Capability::ContentEdit, Capability::ContentDelete]
		};

		return $this === self::Administrator
			? [Role::ALL]
			: array_map(static fn (Capability $capability): string => $capability->value, $capabilities);
	}
}
