<?php

/**
 * Entry status.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

/**
 * Where an entry is in its life. Front matter can set `published`,
 * `draft`, or `trash`; `Scheduled` is derived from a `published` date in
 * the future. Visibility is separate (D-082).
 *
 * Statuses differ in how they're set and where they show (D-484):
 *
 * - **Selectable** ones (`selectable()`) are offered wherever a status is
 *   chosen, such as the editor's status control. `Trash` isn't: Move to
 *   Trash sets it, and Restore takes it off. `Scheduled` isn't either:
 *   a future date sets it.
 * - **Active** ones (`active()`) are what "any status" means: lists,
 *   counts, and queries for every status leave out `Trash` unless they
 *   ask for it by name.
 * - Only `Published` is ever on the site.
 */
enum Status: string
{
	case Published = 'published';
	case Draft     = 'draft';
	case Scheduled = 'scheduled';
	case Trash     = 'trash';

	/**
	 * Returns the values front matter may set.
	 *
	 * @return list<string>
	 */
	public static function writable(): array
	{
		return [self::Published->value, self::Draft->value, self::Trash->value];
	}

	/**
	 * Returns the statuses a status control offers.
	 *
	 * @return list<self>
	 */
	public static function selectable(): array
	{
		return [self::Published, self::Draft];
	}

	/**
	 * Returns every status but `Trash`: what "any status" finds.
	 *
	 * @return list<self>
	 */
	public static function active(): array
	{
		return [self::Published, self::Draft, self::Scheduled];
	}
}
