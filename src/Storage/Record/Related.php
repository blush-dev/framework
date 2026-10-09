<?php

/**
 * Related.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

/**
 * A condition on refs (D-649): the record refers, through a relation,
 * to any of the targets, or, inverse, is referred to by any of them.
 * Targets are ids, or a `Subquery` of ids. Refs are the `refs` table of
 * the record's area (`Ref::table()`).
 */
final readonly class Related
{
	/**
	 * @param  list<string>|Subquery $targets
	 * @throws InvalidRecordQuery When the relation is empty or a target isn't an id.
	 */
	public function __construct(
		public string $relation,
		public array|Subquery $targets,
		public bool $inverse = false
	) {
		if ($relation === '') {
			throw new InvalidRecordQuery('A related condition needs a relation.');
		}

		if (is_array($targets) && ! array_all($targets, static fn (string $id): bool => $id !== '')) {
			throw new InvalidRecordQuery('A related condition\'s targets are ids, or a subquery of them.');
		}
	}
}
