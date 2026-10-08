<?php

/**
 * Record stores.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

use Blush\Storage\StorageArea;
use Blush\Storage\StorageException;
use Blush\Storage\StorageResolver;

/**
 * Where code reaches records (D-643): the `RecordStore` for a table, from
 * the driver its area names in `StorageConfig` (D-642), so a table of
 * accounts and one of data can live with different drivers.
 *
 *     $albums = $stores->query($table)->orderBy('title')->get();
 *     $stores->store($table)->save($table, $record);
 */
final class RecordStores
{
	/**
	 * The stores built so far, by area.
	 *
	 * @var array<string, RecordStore>
	 */
	private array $stores = [];

	public function __construct(
		private readonly StorageResolver $resolver
	) {}

	/**
	 * Returns the store that keeps a table.
	 *
	 * @throws StorageException When the area's driver keeps no records.
	 */
	public function store(Table $table): RecordStore
	{
		return $this->forArea($table->area);
	}

	/**
	 * Returns a query over a table that can run itself.
	 *
	 * @throws StorageException When the area's driver keeps no records.
	 */
	public function query(Table $table): RecordQuery
	{
		return new RecordQuery(store: $this->store($table), table: $table);
	}

	/**
	 * Returns an area's store, built once.
	 *
	 * @throws StorageException
	 */
	private function forArea(StorageArea $area): RecordStore
	{
		return $this->stores[$area->value] ??= $this->resolver->resolve($area, RecordStore::class);
	}
}
