<?php

/**
 * Target lookup interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

/**
 * Finds the entries a relation's written values name (D-589), whatever
 * keeps them: the content index for files, a table later (D-486).
 * Links point at originals, so every id it answers is an original's.
 */
interface TargetLookup
{
	/**
	 * Returns the id of the original of a type's entry a written value
	 * names (a slug, or a tree's path), in a language first, else in any,
	 * or `null` when none does.
	 */
	public function find(string $type, string $written, string $language): ?string;

	/**
	 * Returns the type of the entry with an id, or `null` when there's
	 * none.
	 */
	public function typeOf(string $id): ?string;

	/**
	 * Returns what Blush writes for the entry with an id (its original's
	 * slug, or a tree's path), or `null` when there's none.
	 */
	public function written(string $id): ?string;

	/**
	 * Returns the id of an entry's original when it's a translation (or
	 * its own id when it isn't), or `null` when there's no such entry.
	 */
	public function original(string $id): ?string;
}
