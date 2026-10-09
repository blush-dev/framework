<?php

/**
 * Entry records.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Record;

use Blush\Storage\Record\Operator;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Ref;

/**
 * The `entries` and `refs` records (D-649) as content reads them for
 * what isn't an `Entry`: relations, references, and maintenance (D-654),
 * whatever store keeps them. Entry records come without their content.
 *
 * An entry kept without an id (a 1.x file, D-481) has a steady one as a
 * record (D-652), which `Entries` writes take, but `Entries::find()`
 * doesn't.
 */
final readonly class EntryRecords
{
	public function __construct(
		private RecordStores $stores
	) {}

	/**
	 * Returns a query over the entries, without their content.
	 */
	public function entries(): RecordQuery
	{
		return $this->stores->query(EntryTable::table())->withoutContent();
	}

	/**
	 * Returns a query over the refs.
	 */
	public function refs(): RecordQuery
	{
		return $this->stores->query(Ref::table(EntryTable::table()->area));
	}

	/**
	 * Returns an entry's record, without its content, or `null`.
	 */
	public function find(string $id): ?Record
	{
		return $this->entries()->where('id', Operator::Equal, strtolower($id))->first();
	}

	/**
	 * Returns entries' records, without their content, by id.
	 *
	 * @param  list<string>          $ids
	 * @return array<string, Record>
	 */
	public function findMany(array $ids): array
	{
		if ($ids === []) {
			return [];
		}

		$found = [];

		foreach ($this->entries()->where('id', Operator::In, array_values(array_unique(array_map(strtolower(...), $ids))))->get() as $record) {
			$found[$record->id] = $record;
		}

		return $found;
	}

	/**
	 * Returns every entry's record, without its content, by id.
	 *
	 * @return array<string, Record>
	 */
	public function all(): array
	{
		$found = [];

		foreach ($this->entries()->get() as $record) {
			$found[$record->id] = $record;
		}

		return $found;
	}

	/**
	 * Returns a record's text value, or `''`.
	 */
	public static function text(Record $record, string $key): string
	{
		$value = $record->fields[$key] ?? null;

		return is_string($value) ? $value : '';
	}

	/**
	 * Returns an entry record's front matter.
	 *
	 * @return array<array-key, mixed>
	 */
	public static function front(Record $record): array
	{
		$front = $record->fields['fields'] ?? null;

		return is_array($front) ? $front : [];
	}
}
