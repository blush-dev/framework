<?php

/**
 * Region location.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Region;

/**
 * A place a theme shows a region, declared in `theme.json` `regions`
 * (D-201): a label string, or an object with a `label` and the default
 * `items` it shows when the site has no region file for it.
 *
 * ```json
 * "regions": {
 *     "sidebar": { "label": "Sidebar", "items": [{ "component": "menu", "name": "social" }] },
 *     "footer": "Footer"
 * }
 * ```
 */
final readonly class RegionLocation
{
	/**
	 * @param list<mixed> $items The theme's default items.
	 */
	public function __construct(
		public string $name,
		public string $label = '',
		public array $items = []
	) {}

	/**
	 * Builds a location from its declaration.
	 *
	 * @throws RegionException When it has the wrong shape.
	 */
	public static function fromDeclaration(string $name, mixed $value, string $theme): self
	{
		if (is_string($value)) {
			return new self($name, trim($value));
		}

		if (! is_array($value) || ($value !== [] && array_is_list($value))) {
			throw new RegionException(sprintf('The "%s" theme\'s region location "%s" must be a label or an object.', $theme, $name));
		}

		$label = $value['label'] ?? '';
		$items = $value['items'] ?? [];

		if (! is_string($label)) {
			throw new RegionException(sprintf('The "%s" theme\'s region location "%s" has a "label" that isn\'t a string.', $theme, $name));
		}

		if (! is_array($items) || ! array_is_list($items)) {
			throw new RegionException(sprintf('The "%s" theme\'s region location "%s" has "items" that aren\'t a list.', $theme, $name));
		}

		return new self($name, trim($label), $items);
	}
}
