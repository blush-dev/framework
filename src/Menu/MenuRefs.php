<?php

/**
 * Menu refs.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Menu;

use Blush\Menu\Link\LinksEntry;
use Blush\Menu\Link\MenuLinkFactory;

/**
 * Finds the menu items that link an entry without both forms filed
 * (D-676): with no `ref`, a `ref` that finds nothing, or a readable value
 * that no longer names the entry (it was renamed), and files them: the
 * entry's id in `ref`, right after the link, and its readable value.
 * Items whose entry can't be found are left for `Menus::check()`.
 */
final readonly class MenuRefs
{
	public function __construct(
		private MenuLoader $menus,
		private MenuLinkFactory $links
	) {}

	/**
	 * Returns the items to file, as their positions (`2.1`), by menu name.
	 *
	 * @return array<string, list<string>>
	 * @throws MenuException When the menus can't be read.
	 */
	public function report(): array
	{
		$stale = [];

		foreach ($this->menus->all() as $name => $menu) {
			$positions = [];

			$this->items($menu->items, '', $positions);

			if ($positions !== []) {
				$stale[$name] = $positions;
			}
		}

		return $stale;
	}

	/**
	 * Files every item `report()` lists. Returns the menus filed, and
	 * those that couldn't be, with why.
	 *
	 * @return array{filed: list<string>, failed: array<string, string>}
	 * @throws MenuException When the menus can't be read.
	 */
	public function file(): array
	{
		$filed  = [];
		$failed = [];

		foreach (array_keys($this->report()) as $name) {
			try {
				$this->menus->change($name, function (array $data): array {
					if (is_array($data['items'] ?? null) && array_is_list($data['items'])) {
						$positions     = [];
						$data['items'] = $this->items($data['items'], '', $positions);
					}

					return $data;
				});

				$filed[] = $name;
			} catch (MenuException $error) {
				$failed[$name] = $error->getMessage();
			}
		}

		return ['filed' => $filed, 'failed' => $failed];
	}

	/**
	 * Returns items with their links filed, adding the positions of those
	 * that weren't to `$positions`.
	 *
	 * @param  list<mixed>  $items
	 * @param  list<string> $positions
	 * @return list<mixed>
	 */
	private function items(array $items, string $trail, array &$positions): array
	{
		foreach ($items as $index => $item) {
			if (! is_array($item) || array_is_list($item)) {
				continue;
			}

			$position = ltrim("{$trail}." . ($index + 1), '.');
			$filed    = $this->item($item);

			if ($filed !== $item) {
				$positions[] = $position;
			}

			if (is_array($filed['children'] ?? null) && array_is_list($filed['children'])) {
				$filed['children'] = $this->items($filed['children'], $position, $positions);
			}

			$items[$index] = $filed;
		}

		return $items;
	}

	/**
	 * Returns an item with its link filed in both forms, or as it is when
	 * it doesn't link an entry that can be found.
	 *
	 * @param  array<array-key, mixed> $item
	 * @return array<array-key, mixed>
	 */
	private function item(array $item): array
	{
		foreach ($this->links->keys() as $key) {
			if (! array_key_exists($key, $item) || ! is_string($item[$key])) {
				continue;
			}

			$link = $this->links->make($key);

			$value = trim($item[$key]);

			/** @var array<string, mixed> $item */
			$entry = $link instanceof LinksEntry ? $link->entry($value, $item) : null;

			if ($link instanceof LinksEntry && $entry?->id !== null) {
				$filed = [];

				foreach ($item as $name => $value) {
					if ($name !== LinksEntry::REF) {
						$filed[$name] = $name === $key ? $link->value($entry) : $value;
					}

					if ($name === $key) {
						$filed[LinksEntry::REF] = $entry->id;
					}
				}

				return $filed;
			}

			return $item;
		}

		return $item;
	}
}
