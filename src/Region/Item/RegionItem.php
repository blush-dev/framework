<?php

/**
 * Region item base.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Region\Item;

use Blush\Region\RegionException;
use Blush\Region\RegionRender;
use Blush\View\ViewException;

/**
 * A kind of region item (D-201), keyed in the item by its registered
 * name: `directive: menu`, `component: acme/card`, `markdown: …`,
 * `entry: page/_regions/about`, `view: parts/newsletter`. An item is
 * exactly one kind.
 *
 * Kinds are registered in `RegionItemRegistry` and built through the
 * container, so a kind's constructor can ask for services:
 *
 *     $this->container->make(RegionItemRegistry::class)->register('ad', AdItem::class);
 */
abstract class RegionItem
{
	/**
	 * Returns JSON Schemas for the item keys this kind reads (its own key), by key, for the editor schemas (D-206). `$key`
	 * is the name it's registered under, and `$text` a schema for text or
	 * a locale map of it.
	 *
	 * @param  array<string, mixed> $text
	 * @return array<string, array<string, mixed>>
	 */
	public static function itemSchema(string $key, array $text): array
	{
		return [$key => ['type' => 'string', 'minLength' => 1]];
	}

	/**
	 * Returns what's wrong with an item, or `null` when it has the right
	 * shape.
	 *
	 * @param array<string, mixed> $item The whole item.
	 */
	public function validate(mixed $value, array $item): ?string
	{
		return is_string($value) && trim($value) !== '' ? null : 'must be a non-empty string.';
	}

	/**
	 * Renders an item.
	 *
	 * @param  array<string, mixed> $item The whole item.
	 * @throws RegionException When it can't render.
	 * @throws ViewException
	 */
	abstract public function render(mixed $value, array $item, RegionRender $render): string;

	/**
	 * Returns a validated string value, trimmed.
	 */
	protected static function text(mixed $value): string
	{
		return is_string($value) ? trim($value) : '';
	}
}
