<?php

/**
 * Ref.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

use Blush\Storage\StorageArea;
use Blush\Support\Uuid;

/**
 * One record referring to another through a relation (D-585, D-649):
 * a row of its area's `refs` table, `source_id`, `relation`,
 * `target_id`, and `position` (its place among the source's refs for
 * the relation, from 0). Its id comes from the three ids and names, so
 * the same ref is the same record wherever it's kept.
 */
final readonly class Ref
{
	/**
	 * The refs table's name in each area.
	 */
	public const string TABLE = 'refs';

	public function __construct(
		public string $source,
		public string $relation,
		public string $target,
		public int $position = 0
	) {}

	/**
	 * Returns an area's refs table.
	 */
	public static function table(StorageArea $area): Table
	{
		return new Table(self::TABLE, $area, fields: ['source_id', 'relation', 'target_id', 'position']);
	}

	/**
	 * Returns the ref as its record.
	 */
	public function record(): Record
	{
		return new Record(
			Uuid::fromName("{$this->source}/{$this->relation}/{$this->target}"),
			['source_id' => $this->source, 'relation' => $this->relation, 'target_id' => $this->target, 'position' => $this->position]
		);
	}

	/**
	 * Returns a ref from its record.
	 *
	 * @throws InvalidRecord When the record isn't a ref.
	 */
	public static function fromRecord(Record $record): self
	{
		$source   = $record->fields['source_id'] ?? null;
		$relation = $record->fields['relation'] ?? null;
		$target   = $record->fields['target_id'] ?? null;
		$position = $record->fields['position'] ?? 0;

		if (! is_string($source) || ! is_string($relation) || ! is_string($target) || ! is_int($position)) {
			throw new InvalidRecord(sprintf('The record %s isn\'t a ref: it needs "source_id", "relation", "target_id", and "position".', $record->id));
		}

		return new self($source, $relation, $target, $position);
	}
}
