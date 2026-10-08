<?php

/**
 * Table registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

/**
 * Every table the site keeps (D-643), by name, so tools can reach all of
 * them: copying a site from one driver to another, and a database
 * driver building its schema (D-644). Core and plugins register theirs
 * when the registry is built, in a provider's `register()`:
 *
 *     $this->container->resolving(TableRegistry::class, static function (object $tables): void {
 *         $tables->register(new Table('gallery/albums', StorageArea::Data, key: 'slug'));
 *     });
 */
final class TableRegistry
{
	/**
	 * Tables by name.
	 *
	 * @var array<string, Table>
	 */
	private array $tables = [];

	/**
	 * Registers a table. Registering the same one again is nothing.
	 *
	 * @throws InvalidRecord When another table has its name.
	 */
	public function register(Table $table): void
	{
		$known = $this->tables[$table->name] ?? null;

		if ($known !== null && $known != $table) {
			throw new InvalidRecord(sprintf('A table named "%s" is already registered, defined differently.', $table->name));
		}

		$this->tables[$table->name] = $table;
	}

	/**
	 * Returns a table by name, or `null` when none is registered.
	 */
	public function get(string $name): ?Table
	{
		return $this->tables[$name] ?? null;
	}

	/**
	 * Returns every table, by name.
	 *
	 * @return array<string, Table>
	 */
	public function all(): array
	{
		return $this->tables;
	}
}
