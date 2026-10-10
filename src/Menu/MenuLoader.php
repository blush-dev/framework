<?php

/**
 * Menu loader.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Menu;

use Closure;
use Psr\Clock\ClockInterface;
use Blush\Storage\Record\KeyedTable;
use Blush\Storage\Record\RecordException;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageArea;

/**
 * Reads and writes the site's menus: the `menus` table (D-676), a
 * record per menu keyed by its `name`, with a `label` (optional) and
 * its `items`. On files, each is `user/data/menus/{name}.json`, named
 * for its menu:
 *
 * ```json
 * {
 *     "label": "Primary",
 *     "items": [
 *         {"entry": "page/about", "ref": "0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1c01"},
 *         {"route": "feed", "label": "Feed"}
 *     ]
 * }
 * ```
 *
 * The table is read once. A menu with the wrong shape loads as far as
 * it can, with its problems kept.
 */
final class MenuLoader
{
	/**
	 * The table's name, and its folder under `user/data`.
	 */
	public const string TABLE = 'menus';

	/**
	 * What a menu's name looks like.
	 */
	public const string NAME = '/^[a-z0-9][a-z0-9_-]*$/';

	/**
	 * The menus, by name, once read.
	 *
	 * @var ?array<string, MenuRecord>
	 */
	private ?array $menus = null;

	public function __construct(
		private readonly RecordStores $stores,
		private readonly ClockInterface $clock
	) {}

	/**
	 * The menus' table.
	 */
	public static function table(): Table
	{
		return new Table(self::TABLE, StorageArea::Data, key: 'name', fields: ['name']);
	}

	/**
	 * Returns every menu, by name.
	 *
	 * @return array<string, MenuRecord>
	 * @throws MenuException When the table can't be read.
	 */
	public function all(): array
	{
		if ($this->menus !== null) {
			return $this->menus;
		}

		$records = $this->records();

		try {
			$all = $records->all();
		} catch (RecordException $error) {
			throw new MenuException(sprintf('The menus can\'t be read: %s', $error->getMessage()), 0, $error);
		}

		$menus = [];

		foreach ($all as $name => $data) {
			$menus[$name] = self::menu($name, $records->location($name), $data);
		}

		return $this->menus = $menus;
	}

	/**
	 * Returns a menu, or `null` when the site has none by that name.
	 *
	 * @throws MenuException
	 */
	public function get(string $name): ?MenuRecord
	{
		return $this->all()[$name] ?? null;
	}

	/**
	 * Changes a menu's stored data in a transaction: `$change` gets it
	 * as kept and returns it as it should be.
	 *
	 * @param  Closure(array<string, mixed>): array<string, mixed> $change
	 * @throws MenuException When there's no menu by that name, or it can't be saved.
	 */
	public function change(string $name, Closure $change): void
	{
		$records = $this->records();

		try {
			$records->transaction(static function () use ($records, $name, $change): void {
				$data = $records->find($name) ?? throw new MenuException(sprintf('The site has no menu "%s".', $name));

				$records->save($name, $change($data));
			});
		} catch (RecordException $error) {
			throw new MenuException(sprintf('The menu "%s" couldn\'t be saved in %s: %s', $name, $records->location($name), $error->getMessage()), 0, $error);
		} finally {
			$this->menus = null;
		}
	}

	/**
	 * Writes a menu whole: its `label` (left out when empty) and `items`.
	 * With `$was`, the menu by that name is replaced, and renamed when
	 * `$name` differs, in one transaction.
	 *
	 * @param  list<mixed> $items
	 * @throws MenuException When the name isn't valid or is taken, there's no menu `$was`, or it can't be saved.
	 */
	public function save(string $name, mixed $label, array $items, ?string $was = null): void
	{
		if (preg_match(self::NAME, $name) !== 1) {
			throw new MenuException(sprintf('"%s" isn\'t a valid menu name: use lowercase letters, digits, hyphens, and underscores.', $name));
		}

		$records = $this->records();
		$data    = $label === null || $label === '' ? ['items' => $items] : ['label' => $label, 'items' => $items];

		try {
			$records->transaction(static function () use ($records, $name, $was, $data): void {
				if ($was !== null && ! $records->has($was)) {
					throw new MenuException(sprintf('The site has no menu "%s".', $was));
				}

				if ($was !== $name && $records->has($name)) {
					throw new MenuException(sprintf('The site already has a menu named "%s".', $name));
				}

				if ($was !== null && $was !== $name) {
					$records->delete($was);
				}

				$records->save($name, $data);
			});
		} catch (RecordException $error) {
			throw new MenuException(sprintf('The menu "%s" couldn\'t be saved in %s: %s', $name, $records->location($name), $error->getMessage()), 0, $error);
		} finally {
			$this->menus = null;
		}
	}

	/**
	 * Removes a menu. A missing one is nothing to remove.
	 *
	 * @throws MenuException When it can't be removed.
	 */
	public function delete(string $name): void
	{
		$records = $this->records();

		try {
			$records->delete($name);
		} catch (RecordException $error) {
			throw new MenuException(sprintf('The menu "%s" couldn\'t be removed from %s: %s', $name, $records->location($name), $error->getMessage()), 0, $error);
		} finally {
			$this->menus = null;
		}
	}

	/**
	 * Where a menu is kept, for people: `user/data/menus/primary.json`
	 * for a file.
	 */
	public function location(string $name): string
	{
		return $this->records()->location($name);
	}

	/**
	 * The table's records.
	 */
	private function records(): KeyedTable
	{
		return new KeyedTable($this->stores, self::table(), $this->clock);
	}

	/**
	 * Builds a menu from its record's data.
	 *
	 * @param array<string, mixed> $data
	 */
	private static function menu(string $name, string $location, array $data): MenuRecord
	{
		$problems = [];

		if (preg_match(self::NAME, $name) !== 1) {
			$problems[] = 'Its name isn\'t valid: use lowercase letters, digits, hyphens, and underscores.';
		}

		foreach (array_keys(array_diff_key($data, ['label' => true, 'items' => true])) as $key) {
			$problems[] = sprintf('"%s" isn\'t a menu key; a menu has "label" and "items".', $key);
		}

		$items = $data['items'] ?? [];

		if (! is_array($items) || ! array_is_list($items)) {
			$problems[] = '"items" must be a list.';
			$items      = [];
		}

		return new MenuRecord($name, $location, $data['label'] ?? null, $items, $problems);
	}
}
