<?php

/**
 * Token scope.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

/**
 * What a token may do, as its presets say it (D-750, D-752): read what
 * anyone may, write drafts and upload media, or everything its account
 * may. An OAuth client asks for one by name (`read`, `drafts`,
 * `account`), and the MCP server offers a plugin's tools by it (D-757).
 * A token narrowed to capabilities of someone's own choosing is
 * `Account` here: what it may do is what they chose.
 */
enum TokenScope: string
{
	case Read    = 'read';
	case Drafts  = 'drafts';
	case Account = 'account';

	/**
	 * The capabilities a token of the scope is narrowed to, or `null`
	 * for its account's own.
	 *
	 * @return ?list<string>
	 */
	public function capabilities(): ?array
	{
		return match ($this) {
			self::Read    => [],
			self::Drafts  => ['content.*.create', 'content.*.edit', 'content.*.edit.others', 'media.*.upload', 'media.edit'],
			self::Account => null
		};
	}

	/**
	 * Returns the scope's name, as the admin shows it.
	 */
	public function label(): string
	{
		return match ($this) {
			self::Read    => 'Read',
			self::Drafts  => 'Write drafts',
			self::Account => 'Same as your account'
		};
	}

	/**
	 * Returns the scope a token's capabilities match, or `Account` for
	 * none of the presets.
	 *
	 * @param ?list<string> $capabilities
	 */
	public static function of(?array $capabilities): self
	{
		$sorted = static function (?array $list): ?array {
			if ($list === null) {
				return null;
			}

			sort($list);

			return $list;
		};

		return array_find(self::cases(), static fn (self $scope): bool => $sorted($scope->capabilities()) === $sorted($capabilities)) ?? self::Account;
	}

	/**
	 * Whether the scope takes in another: `account` all of them,
	 * `drafts` itself and `read`.
	 */
	public function covers(self $other): bool
	{
		$rank = static fn (self $scope): int => match ($scope) {
			self::Read    => 0,
			self::Drafts  => 1,
			self::Account => 2
		};

		return $rank($this) >= $rank($other);
	}
}
