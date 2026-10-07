<?php

/**
 * Menu item.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Menu;

use Blush\View\SafeHtml;
use Blush\View\TrustedHtml;

/**
 * One resolved menu item, as templates see it (D-199, D-200): its label
 * in the page's locale, its URL (`null` for a heading that only groups
 * its children), the built-in presentation fields, the theme-declared
 * `fields`, and its children.
 *
 * `current` marks the item for the page being rendered
 * (`aria-current="page"`), and `ancestor` marks the items above it. Print
 * every value with the escaping helpers: `e($item->label)`,
 * `url($item->url)`.
 */
final readonly class MenuItem
{
	/**
	 * @param array<string, mixed> $fields   Theme-declared fields' typed values, by name.
	 * @param list<MenuItem>       $children
	 */
	public function __construct(
		public string $label,
		public ?string $url = null,
		public string $icon = '',
		public string $description = '',
		public string $image = '',
		public string $badge = '',
		public string $class = '',
		public string $rel = '',
		public array $fields = [],
		public array $children = [],
		public bool $current = false,
		public bool $ancestor = false
	) {}

	/**
	 * Returns a theme-declared field's value, or a default.
	 */
	public function field(string $name, mixed $default = null): mixed
	{
		return $this->fields[$name] ?? $default;
	}

	/**
	 * Returns whether the item has children.
	 */
	public function hasChildren(): bool
	{
		return $this->children !== [];
	}

	/**
	 * Returns whether the item links somewhere, rather than only grouping
	 * its children.
	 */
	public function isLink(): bool
	{
		return $this->url !== null;
	}

	/**
	 * Returns whether the item or any item below it is current.
	 */
	public function isActive(): bool
	{
		return $this->current || $this->ancestor;
	}

	/**
	 * Returns the item's `aria-current` attribute, ready to print in its
	 * link: `aria-current="page"` for the page being rendered,
	 * `aria-current="true"` for an item above it (its section), or
	 * nothing: `<a href="…" <?= $item->ariaCurrent() ?>>`.
	 */
	public function ariaCurrent(): SafeHtml
	{
		return new TrustedHtml(match (true) {
			$this->current  => 'aria-current="page"',
			$this->ancestor => 'aria-current="true"',
			default         => ''
		});
	}

	/**
	 * Returns a copy marked for a page: current when its URL is the page's
	 * path (written as a path or a full URL on `$origin`), and an ancestor
	 * when an item below it is current.
	 */
	public function forPath(string $path, string $origin): self
	{
		$children = array_map(static fn (MenuItem $child): MenuItem => $child->forPath($path, $origin), $this->children);
		$current  = $path !== '' && $this->url !== null && self::normalize($this->url, $origin) === self::normalize($path, $origin);

		return new self(
			$this->label,
			$this->url,
			$this->icon,
			$this->description,
			$this->image,
			$this->badge,
			$this->class,
			$this->rel,
			$this->fields,
			$children,
			$current,
			array_any($children, static fn (MenuItem $child): bool => $child->isActive())
		);
	}

	/**
	 * Returns a URL's path on the site, without its query, fragment, or
	 * trailing slash, or `null` for a URL elsewhere.
	 */
	private static function normalize(string $url, string $origin): ?string
	{
		if ($origin !== '' && str_starts_with(strtolower($url), strtolower($origin))) {
			$url = substr($url, strlen($origin));

			if ($url === '' || ! in_array($url[0], ['/', '?', '#'], true)) {
				$url = '/' . $url;
			}
		}

		if (! str_starts_with($url, '/') || str_starts_with($url, '//')) {
			return null;
		}

		$path = preg_split('/[?#]/', $url, 2)[0] ?? $url;

		return rtrim($path, '/') ?: '/';
	}
}
