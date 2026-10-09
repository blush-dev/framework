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
 * and the fields it declares.
 *
 *     new Table('roles', StorageArea::Accounts, key: 'name');
 *
 * - **The key** (D-646) is a value unique in the table, such as `name`
 *   or `username`, that lookups use beside the id and the filesystem
 *   driver names files by. Its values are letters, digits, `.`, `_`, and
 *   `-`, starting with a letter or digit. A table without one is known
 *   by ids alone.
 * - **A path key** (`pathKey`, D-679) holds a site's paths instead, such
 *   as a redirect's `from` (`/news/{name}`): `/` then anything but
 *   whitespace. Files can't be named by it, so the filesystem driver
 *   keeps such a table as one file.
 * - **The declared fields** are the ones a database driver indexes
 *   (D-644, D-648): those queried or sorted by. Others are kept and
 *   queried all the same.
 *
 * Every area can keep tables. Sessions and jobs are reached only through
 * their narrow stores (D-645), whose database versions keep theirs in
 * tables of their area (`sessions`, `jobs`; D-662). Names are lowercase
 * letters, digits, `_`, and `-`, and may use `/` to group tables
 * (`gallery/albums`).
 */
final readonly class Table
{
	/**
	 * @param  list<string> $fields  The declared fields' keys.
	 * @param  bool         $pathKey Whether the key's values are paths.
	 * @throws InvalidRecord When the name, area, or key can't be a table's.
	 */
	public function __construct(
		public string $name,
		public StorageArea $area,
		public ?string $key = null,
		public array $fields = [],
		public bool $pathKey = false
	) {
		if (preg_match('/\A[a-z][a-z0-9_-]*(?:\/[a-z][a-z0-9_-]*)*\z/', $name) !== 1) {
			throw new InvalidRecord(sprintf('"%s" can\'t be a table name; use lowercase letters, digits, "_", and "-", with "/" between groups.', $name));
		}

		if ($key !== null && ($key === '' || in_array($key, Record::RESERVED, true))) {
			throw new InvalidRecord(sprintf('"%s" can\'t be a table\'s key.', $key));
		}

		if ($pathKey && $key === null) {
			throw new InvalidRecord(sprintf('"%s" has no key, so its key can\'t hold paths.', $name));
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

		$value = $record->fields[$this->key] ?? null;

		if (! is_string($value) || ! $this->isKey($value)) {
			throw new InvalidRecord(sprintf(
				$this->pathKey
					? 'A record in "%s" needs a "%s" that\'s a path: "/" then no spaces; %s given.'
					: 'A record in "%s" needs a "%s" of letters, digits, ".", "_", and "-", starting with a letter or digit; %s given.',
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
			if ($other->id !== $record->id && ($other->fields[$name] ?? null) === $key) {
				throw new InvalidRecord(sprintf('"%s" already has a record whose "%s" is "%s".', $this->name, $name, $key));
			}
		}
	}

	/**
	 * Returns whether a value can be this table's key: a path for a path
	 * key, else what `isKeyValue()` allows.
	 */
	public function isKey(string $value): bool
	{
		return $this->pathKey ? preg_match('/\A\/\S*\z/u', $value) === 1 : self::isKeyValue($value);
	}

	/**
	 * Returns whether a value can be a key's.
	 */
	public static function isKeyValue(string $value): bool
	{
		return preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/', $value) === 1;
	}
}
