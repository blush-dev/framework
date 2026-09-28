<?php

/**
 * Menu.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Menu;

/**
 * A resolved menu for one location, as templates see it (D-199): the
 * location's name, the site menu that fills it, the navigation's label,
 * and its items, with the links that don't resolve left out.
 *
 * ```php
 * <?php if ($menu = $template->menu('social')) : ?>
 *     <?php foreach ($menu->items as $item) : ?>
 *         <a href="<?= url($item->url) ?>"><?= e($item->label) ?></a>
 *     <?php endforeach ?>
 * <?php endif ?>
 * ```
 */
final readonly class Menu
{
	/**
	 * @param string         $location The theme location it fills.
	 * @param string         $name     The site menu's name (`user/data/menus/{name}`).
	 * @param string         $label    The navigation's accessible name.
	 * @param list<MenuItem> $items
	 */
	public function __construct(
		public string $location,
		public string $name,
		public string $label,
		public array $items = []
	) {}

	/**
	 * Returns a copy with the item for a page's path marked current, and
	 * the items above it as ancestors.
	 */
	public function forPath(string $path, string $origin = ''): self
	{
		return new self(
			$this->location,
			$this->name,
			$this->label,
			array_map(static fn (MenuItem $item): MenuItem => $item->forPath($path, $origin), $this->items)
		);
	}

	/**
	 * Returns the current item, at any depth, or `null`.
	 */
	public function current(): ?MenuItem
	{
		return self::find($this->items);
	}

	/**
	 * Returns the current item among items and their children.
	 *
	 * @param list<MenuItem> $items
	 */
	private static function find(array $items): ?MenuItem
	{
		foreach ($items as $item) {
			if ($item->current) {
				return $item;
			}

			if ($item->ancestor) {
				return self::find($item->children);
			}
		}

		return null;
	}
}
