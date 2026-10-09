<?php

/**
 * Definition tables.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use Psr\Clock\ClockInterface;
use Blush\Storage\Record\KeyedTable;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageArea;

/**
 * The tables of the content types and relations a site defines (D-672):
 * `types` and `relations`, keyed by `name`, in the data area. On files,
 * each is a folder of JSON files named for their records
 * (`user/data/types/movie.json`, `user/data/relations/actors.json`), the
 * name never written inside; a record's other fields are its options,
 * as written. Types and relations code defines aren't kept here.
 */
final readonly class DefinitionTables
{
	/**
	 * The types' table's name, and its folder under `user/data`.
	 */
	public const string TYPES = 'types';

	/**
	 * The relations' table's name, and its folder under `user/data`.
	 */
	public const string RELATIONS = 'relations';

	public function __construct(
		private RecordStores $stores,
		private ClockInterface $clock
	) {}

	/**
	 * The types' table.
	 */
	public static function typesTable(): Table
	{
		return new Table(self::TYPES, StorageArea::Data, key: 'name', fields: ['name']);
	}

	/**
	 * The relations' table.
	 */
	public static function relationsTable(): Table
	{
		return new Table(self::RELATIONS, StorageArea::Data, key: 'name', fields: ['name']);
	}

	/**
	 * The site's types, by name.
	 */
	public function types(): KeyedTable
	{
		return new KeyedTable($this->stores, self::typesTable(), $this->clock);
	}

	/**
	 * The site's relations, by name.
	 */
	public function relations(): KeyedTable
	{
		return new KeyedTable($this->stores, self::relationsTable(), $this->clock);
	}
}
