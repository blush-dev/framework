<?php

/**
 * Menu block.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Block;

use Override;
use Blush\Core\AppConfig;
use Blush\Core\Framework;
use Blush\Menu\Menu as SiteMenu;
use Blush\Menu\MenuItem;
use Blush\Menu\Menus;
use Blush\Theme\ThemeResolver;

/**
 * A site menu in a `<nav>` (D-199, D-200): what a theme location shows,
 * by `location`, a site menu by its `name` (D-676), or a `Menu` given as
 * `menu` (from `$template->menu()`). Nothing renders when there's no
 * menu to show.
 *
 * ```php
 * <?= $template->block('menu', location: 'primary') ?>
 * ```
 *
 * In Markdown, `::menu{name=social}`, by the menu's name, since content
 * doesn't know the theme's locations. A menu in an entry's body is
 * rendered once for every page, so none of its items is marked current.
 *
 * The root is `block-menu` with the location (else the menu's name)
 * as a modifier (`block-menu--primary`). Items with children get a disclosure
 * button next to their link, `hidden` until a theme's script shows it
 * and toggles the submenu (the behavior is the theme's); without one,
 * submenus stay open.
 */
final class Menu extends Block
{
	/**
	 * @inheritDoc
	 */
	public const ?BlockKind KIND = BlockKind::Leaf;

	/**
	 * The menu, once resolved.
	 */
	private ?SiteMenu $resolved = null;

	public function __construct(
		private readonly Menus $menus,
		private readonly ThemeResolver $themes,
		private readonly AppConfig $app,
		public readonly string $location = '',
		public readonly string $name = '',
		public readonly string $label = '',
		public readonly ?SiteMenu $menu = null
	) {}

	/**
	 * Returns the menu, with the page's item marked current, or `null`.
	 */
	public function menu(): ?SiteMenu
	{
		if ($this->menu !== null) {
			return $this->menu;
		}

		if ($this->resolved === null && (trim($this->location) !== '' || trim($this->name) !== '')) {
			$context        = $this->context();
			$chain          = $this->themes->current();
			$menu           = trim($this->location) !== ''
				? $this->menus->forLocation($chain, trim($this->location), $context->locale ?? '')
				: $this->menus->named($chain, trim($this->name), $context->locale ?? '');
			$this->resolved = $menu?->forPath($context->path ?? '', $this->app->origin());
		}

		return $this->resolved;
	}

	/**
	 * Returns the menu's top-level items.
	 *
	 * @return list<MenuItem>
	 */
	public function items(): array
	{
		return $this->menu()->items ?? [];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function shouldRender(): bool
	{
		return $this->items() !== [];
	}

	/**
	 * Returns the navigation's name: the `label` prop, else the menu's or
	 * its location's label, else the theme's `menu.label` text.
	 */
	public function navLabel(): string
	{
		foreach ([$this->label, $this->menu()->label ?? ''] as $label) {
			if (trim($label) !== '') {
				return trim($label);
			}
		}

		return (string) $this->messages->t('menu.label');
	}

	/**
	 * Returns a list's attributes, escaped: `block-menu__list`, and
	 * for a submenu (with its `$trail`, the item's position such as
	 * `2-1`) `block-menu__submenu` and the ID its toggle controls.
	 */
	public function listAttributes(string $trail = ''): string
	{
		return self::html([
			'class' => $this->elementClass('list')->with($trail === '' ? [] : [(string) $this->elementClass('submenu')]),
			'id'    => $trail === '' ? null : $this->submenuId($trail)
		]);
	}

	/**
	 * Returns an item's (`<li>`) attributes, escaped: `block-menu__item`,
	 * with `--current`, `--ancestor`, and `--parent` modifiers, and the
	 * item's own `class`.
	 */
	public function itemAttributes(MenuItem $item): string
	{
		return self::html([
			'class' => $this->elementClass('item')
				->when($item->current, 'current')
				->when($item->ancestor, 'ancestor')
				->when($item->hasChildren(), 'parent')
				->with($item->class)
		]);
	}

	/**
	 * Returns an item's link (`<a>`) attributes, escaped: its class, URL,
	 * `aria-current="page"` when it's the page, and `rel`. For an item
	 * without a link, the attributes of the heading that stands in for it
	 * (`block-menu__heading`).
	 */
	public function linkAttributes(MenuItem $item): string
	{
		if (! $item->isLink()) {
			return self::html(['class' => $this->elementClass('heading')]);
		}

		return self::html([
			'class'        => $this->elementClass('link'),
			'href'         => $item->url,
			'aria-current' => $item->current ? 'page' : null,
			'rel'          => $item->rel
		]);
	}

	/**
	 * Returns a submenu's disclosure button attributes, escaped: its
	 * class, `aria-expanded` (open when the page is inside it), the
	 * submenu it controls, its accessible name (the theme's `menu.toggle`
	 * text), and `hidden`, which a theme's script removes.
	 */
	public function toggleAttributes(MenuItem $item, string $trail): string
	{
		return self::html([
			'class'         => $this->elementClass('toggle'),
			'type'          => 'button',
			'aria-expanded' => $item->ancestor ? 'true' : 'false',
			'aria-controls' => $this->submenuId($trail),
			'aria-label'    => (string) $this->messages->t('menu.toggle', label: $item->label),
			'hidden'        => true
		]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function modifiers(): array
	{
		$modifier = $this->menu()->location ?? '';
		$modifier = $modifier !== '' ? $modifier : $this->menu()->name ?? '';

		return $modifier === '' ? [] : [$modifier];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function rootAttributes(): array
	{
		return ['aria-label' => $this->navLabel()];
	}

	/**
	 * Returns a submenu's ID, from the root's ID (or the modifier) and the
	 * item's position.
	 */
	private function submenuId(string $trail): string
	{
		$base = $this->id !== '' ? $this->id : 'menu-' . ($this->modifiers()[0] ?? 'nav');

		return "{$base}-{$trail}";
	}

	/**
	 * Renders the framework's template for it, `resources/blocks/menu.php`
	 * (D-382), when the theme chain has none of its own.
	 */
	#[Override]
	public function render(): BlockView
	{
		return $this->view(Framework::path('resources/blocks/menu.php'));
	}
}
