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
 * - `author` (D-043, D-329): entries in `user/content/authors`, the
 *   people that the entries of types supporting authors credit through
 *   `authors` (or the 1.x `author`). It has no routes of its own.
 *
 * The site can redefine either in `config/content.php` or as a data type,
 * and can disable `author`.
 */
enum BuiltInType: string
{
	case Page   = 'page';
	case Author = 'author';

	/**
	 * Returns the type's default definition.
	 */
	public function type(): ContentType
	{
		return match ($this) {
			self::Page   => new Pages(),
			self::Author => new Authors('author', folder: 'authors', field: 'authors', aliases: ['author'])
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
