<?php

/**
 * Table.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

use Blush\Storage\StorageArea;

/**
 * A named group of records (D-643), as a driver needs to know it: its
 * name, the storage area it belongs to (whose driver keeps it), its key,
 * and the values it declares.
 *
 *     new Table('roles', StorageArea::Accounts, key: 'name');
 *
 * - **The key** (D-646) is a value unique in the table, such as `name`
 *   or `username`, that lookups use beside the id and the filesystem
 *   driver names files by. Its values are letters, digits, `.`, `_`, and
 *   `-`, starting with a letter or digit. A table without one is known
 *   by ids alone.
 * - **The declared values** are the ones a database driver gives an
 *   indexed column (D-644). Others are kept and queried all the same.
 *
 * Tables belong to the content, data, and accounts areas; sessions and
 * jobs keep their own narrow stores (D-645). Names are lowercase
 * letters, digits, `_`, and `-`, and may use `/` to group tables
 * (`gallery/albums`).
 */
final readonly class Table
{
	/**
	 * @param  list<string> $values The declared values' keys.
	 * @throws InvalidRecord When the name, area, or key can't be a table's.
	 */
	public function __construct(
		public string $name,
		public StorageArea $area,
		public ?string $key = null,
		public array $values = []
	) {
		if (preg_match('/\A[a-z][a-z0-9_-]*(?:\/[a-z][a-z0-9_-]*)*\z/', $name) !== 1) {
			throw new InvalidRecord(sprintf('"%s" can\'t be a table name; use lowercase letters, digits, "_", and "-", with "/" between groups.', $name));
		}

		if (! in_array($area, [StorageArea::Content, StorageArea::Data, StorageArea::Accounts], true)) {
			throw new InvalidRecord(sprintf('The %s area keeps no tables (D-645).', $area->value));
		}

		if ($key !== null && ($key === '' || in_array($key, Record::RESERVED, true))) {
			throw new InvalidRecord(sprintf('"%s" can\'t be a table\'s key.', $key));
		}
	}

	/**
	 * Returns a record's key value, or `null` for a table without a key.
	 *
	 * @throws InvalidRecord When the record's key value is missing or malformed.
	 */
	public function keyOf(Record $record): ?string
	{
		if ($this->key === null) {
			return null;
		}

		$value = $record->values[$this->key] ?? null;

		if (! is_string($value) || ! self::isKeyValue($value)) {
			throw new InvalidRecord(sprintf(
				'A record in "%s" needs a "%s" of letters, digits, ".", "_", and "-", starting with a letter or digit; %s given.',
				$this->name,
				$this->key,
				is_string($value) ? "\"{$value}\"" : get_debug_type($value)
			));
		}

		return $value;
	}

	/**
	 * Checks that a record's key value, if the table has a key, is
	 * well-formed and no other record's.
	 *
	 * @param  iterable<Record> $records The table's records.
	 * @throws InvalidRecord
	 */
	public function checkKey(Record $record, iterable $records): void
	{
		$key = $this->keyOf($record);

		if ($key === null) {
			return;
		}

		$name = (string) $this->key;

		foreach ($records as $other) {
			if ($other->id !== $record->id && ($other->values[$name] ?? null) === $key) {
				throw new InvalidRecord(sprintf('"%s" already has a record whose "%s" is "%s".', $this->name, $name, $key));
			}
		}
	}

	/**
	 * Returns whether a value can be a key's.
	 */
	public static function isKeyValue(string $value): bool
	{
		return preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/', $value) === 1;
	}
}
