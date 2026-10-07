<?php

/**
 * Link.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

/**
 * One link between two entries in a relation (D-585): the record a
 * database would keep (D-486). Entries are named by id, never by path
 * or slug, so a rename or a move doesn't change it. A target is always
 * an original, never a translation; a link is shown in the reader's
 * language when the target has a translation in it (D-587).
 *
 * @phpstan-type LinkArray array{source: string, type: string, relation: string, target: string, targetType: string, position: int}
 */
final readonly class Link
{
	/**
	 * @param string $source     The source entry's id.
	 * @param string $type       The source entry's type.
	 * @param string $relation   The relation's name.
	 * @param string $target     The target entry's id.
	 * @param string $targetType The target entry's type.
	 * @param int    $position   The target's place among the source's, from 0.
	 */
	public function __construct(
		public string $source,
		public string $type,
		public string $relation,
		public string $target,
		public string $targetType,
		public int $position = 0
	) {}

	/**
	 * Returns the relation's key on the source's type (`movie.actors`).
	 */
	public function key(): string
	{
		return "{$this->type}.{$this->relation}";
	}

	/**
	 * Builds a link from `toArray()`'s array.
	 *
	 * @param LinkArray $data
	 */
	public static function fromArray(array $data): self
	{
		return new self($data['source'], $data['type'], $data['relation'], $data['target'], $data['targetType'], $data['position']);
	}

	/**
	 * Returns the link as an array for storage.
	 *
	 * @return LinkArray
	 */
	public function toArray(): array
	{
		return [
			'source'     => $this->source,
			'type'       => $this->type,
			'relation'   => $this->relation,
			'target'     => $this->target,
			'targetType' => $this->targetType,
			'position'   => $this->position
		];
	}
}
