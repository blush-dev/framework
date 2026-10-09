<?php

/**
 * Menu link base.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Menu\Link;

use Blush\Support\Uuid;

/**
 * A kind of menu item link (D-199), keyed in the item by its registered
 * name: `entry: page/about`, `route: feed`, `url: https://…`. An item has
 * at most one link.
 *
 * Kinds are registered in `MenuLinkRegistry` and built through the
 * container, so a kind's constructor can ask for services:
 *
 *     $this->container->make(MenuLinkRegistry::class)->register('product', ProductLink::class);
 */
abstract class MenuLink
{
	/**
	 * Returns JSON Schemas for the item keys this kind reads (its own key
	 * and any in `keys()`), by key, for the editor schemas (D-206). `$key`
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
	 * Returns the other item keys this kind reads, such as a route's
	 * `params`, so they aren't taken for theme-declared fields.
	 *
	 * @return list<string>
	 */
	public function keys(): array
	{
		return [];
	}

	/**
	 * Returns what's wrong with an item's link value, or `null` when it
	 * has the right shape. Checked when a menu loads; whether it leads
	 * anywhere is `resolve()`'s job.
	 *
	 * @param array<string, mixed> $item The whole item.
	 */
	public function validate(mixed $value, array $item): ?string
	{
		return is_string($value) && trim($value) !== '' ? null : 'must be a non-empty string.';
	}

	/**
	 * Returns where a link leads, in a locale.
	 *
	 * @param  array<string, mixed> $item The whole item.
	 * @throws UnresolvedLink When it doesn't lead anywhere now.
	 */
	abstract public function resolve(string $value, array $item, string $locale): LinkTarget;

	/**
	 * Returns the JSON Schema of an item's `ref`, for kinds that link an
	 * entry (`LinksEntry`).
	 *
	 * @return array<string, mixed>
	 */
	protected static function refSchema(): array
	{
		return [
			'type'        => 'string',
			'pattern'     => '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$',
			'description' => 'The id of the entry it links to, which wins over the readable form when it finds one. bin/blush menu:refs --write fills it in.'
		];
	}

	/**
	 * Returns whether an item's `ref`, if it has one, is an id.
	 *
	 * @param array<string, mixed> $item
	 */
	protected static function validRef(array $item): bool
	{
		$ref = $item[LinksEntry::REF] ?? null;

		return $ref === null || (is_string($ref) && Uuid::isValid($ref));
	}
}
