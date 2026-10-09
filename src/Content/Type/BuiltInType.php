<?php

/**
 * Built-in content types.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

/**
 * The content types the framework registers:
 *
 * - `page`: everything in `user/content` no other type claims, routed by
 *   the page catch-all rather than routes of its own.
 * - `profile` (D-043, D-351): entries in `user/content/_profile` (D-683,
 *   D-684), the people other types credit through credit relations (D-602), such as
 *   `authors` (read from 1.x's `author` too). Each has a page at
 *   `/profiles/{slug}`.
 *
 * The site can redefine either as a data type, and can disable `profile`.
 */
enum BuiltInType: string
{
	case Page    = 'page';
	case Profile = 'profile';

	/**
	 * Returns the type's default definition.
	 */
	public function type(): ContentType
	{
		return match ($this) {
			self::Page    => new Tree(),
			self::Profile => new Profiles()
		};
	}

	/**
	 * Returns whether the site may disable the type. Pages can't be
	 * disabled; every file needs a type to fall back on.
	 */
	public function canDisable(): bool
	{
		return $this !== self::Page;
	}
}
