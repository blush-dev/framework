<?php

/**
 * Redirects repository.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Routing;

use Closure;
use Psr\Clock\ClockInterface;
use Blush\Container\Attributes\Defer;
use Blush\Core\AppConfig;
use Blush\Routing\InvalidRoute;
use Blush\Routing\RouteCache;
use Blush\Storage\Record\KeyedTable;
use Blush\Storage\Record\LocatingStore;
use Blush\Storage\Record\RecordException;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordStores;

/**
 * The site owner's redirects, the `redirects` table's rows
 * (`DataRedirects::table()`, D-678), read and written as `RedirectRow`s
 * by their `from` path. Writes go through `write()`, which runs them in
 * a transaction and then writes an existing compiled route table again
 * outside development, so a change works without a `cache:compile`.
 */
final readonly class Redirects
{
	/**
	 * @param Closure(): RouteCache $routes Deferred: the route table is compiled from these rows.
	 */
	public function __construct(
		private RecordStores $stores,
		private ClockInterface $clock,
		#[Defer(RouteCache::class)] private Closure $routes,
		private AppConfig $app
	) {}

	/**
	 * Every row, in the order the table keeps them, which is the order
	 * the route table tries patterns in.
	 *
	 * @return list<RedirectRow>
	 * @throws RecordException When the table can't be read.
	 * @throws InvalidRoute When a row isn't one, saying where it's kept.
	 */
	public function all(): array
	{
		$table = DataRedirects::table();
		$store = $this->stores->store($table);
		$rows  = [];

		foreach ($store->select($table, new RecordQuery())->records as $record) {
			try {
				$rows[] = RedirectRow::fromArray($record->fields);
			} catch (InvalidRoute $e) {
				$from  = $record->fields['from'] ?? null;
				$where = $store instanceof LocatingStore ? $store->location($table, is_string($from) ? $from : $record->id) : DataRedirects::TABLE;

				throw new InvalidRoute(sprintf('%s: %s', $where, $e->getMessage()), previous: $e);
			}
		}

		return $rows;
	}

	/**
	 * The row from a path, or `null`.
	 *
	 * @throws RecordException
	 * @throws InvalidRoute
	 */
	public function find(string $from): ?RedirectRow
	{
		$data = $this->table()->find($from);

		return $data === null ? null : RedirectRow::fromArray([...$data, 'from' => $from]);
	}

	/**
	 * Writes a row, replacing the one from its path.
	 *
	 * @throws RecordException
	 */
	public function save(RedirectRow $row): void
	{
		$this->table()->save($row->from, $row->toArray());
	}

	/**
	 * Removes the row from a path. A missing one is nothing to remove.
	 *
	 * @throws RecordException
	 */
	public function delete(string $from): void
	{
		$this->table()->delete($from);
	}

	/**
	 * Runs writes in a transaction, putting back what they wrote when one
	 * throws, then writes an existing compiled route table again outside
	 * development.
	 *
	 * @template T
	 * @param  Closure(): T $write
	 * @return T
	 * @throws RecordException When the table can't be written.
	 * @throws InvalidRoute When the compiled route table can't be written.
	 */
	public function write(Closure $write): mixed
	{
		$result = $this->table()->transaction($write);

		$routes = ($this->routes)();

		if (! $this->app->environment->isDevelopment() && is_file($routes->path())) {
			$routes->write();
		}

		return $result;
	}

	/**
	 * Whether a path can be a row's `from` or a path `to`: a path key
	 * (D-679), `/` then no whitespace.
	 */
	public function isPath(string $path): bool
	{
		return DataRedirects::table()->isKey($path);
	}

	/**
	 * The table, by key.
	 *
	 * @throws RecordException
	 */
	private function table(): KeyedTable
	{
		return new KeyedTable($this->stores, DataRedirects::table(), $this->clock);
	}
}
