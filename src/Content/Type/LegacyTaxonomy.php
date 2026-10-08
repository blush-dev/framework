<?php

/**
 * Legacy taxonomy.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

/**
 * Turns a taxonomy definition (`kind: taxonomy`, or 1.x's `taxonomy:
 * true`) into what replaced it (D-591, D-593): a collection of terms and
 * the classify relation that files entries under them. The migration
 * tool writes both (`content:taxonomies`, Site Health); until it has,
 * the loader reads a data type in this form through it so the site and
 * the admin keep working.
 *
 * - **The collection** keeps every option a collection has, ordered by
 *   `position` then title, with no people and out of `llms.txt` unless
 *   the taxonomy said otherwise, as taxonomies were.
 * - **The relation** is named after it, from the taxonomy's `types`
 *   (1.x's `term_collect`; every type when empty), read from its `field`
 *   and `aliases`, creating terms as they're typed, its terms' pages
 *   listing what uses them as `termListing` (1.x's `term_collection`)
 *   said, unless the taxonomy had no URLs.
 */
final class LegacyTaxonomy
{
	/**
	 * The options only a taxonomy had, which the relation takes.
	 *
	 * @var list<string>
	 */
	private const array RELATION_OPTIONS = ['taxonomy', 'kind', 'types', 'term_collect', 'field', 'aliases', 'field_aliases', 'termListing', 'term_collection'];

	/**
	 * Returns whether a definition is a taxonomy.
	 *
	 * @param array<array-key, mixed> $definition
	 */
	public static function is(array $definition): bool
	{
		return ($definition['kind'] ?? null) === 'taxonomy' || ($definition['taxonomy'] ?? null) === true;
	}

	/**
	 * Returns the collection's definition and the relation's for a
	 * taxonomy named `$name`.
	 *
	 * @param  array<array-key, mixed> $definition
	 * @return array{array<array-key, mixed>, array<string, mixed>}
	 */
	public static function convert(string $name, array $definition): array
	{
		$type = array_diff_key($definition, array_flip(self::RELATION_OPTIONS));

		$type['order'] ??= TypeOrder::Position->value;
		$type['llms']  ??= false;

		// Terms credit nobody; a type credits people through relations now
		// (D-602).
		unset($type['people'], $type['authors']);

		$from    = $definition['types'] ?? $definition['term_collect'] ?? [];
		$field   = $definition['field'] ?? $name;
		$aliases = $definition['aliases'] ?? $definition['field_aliases'] ?? [];
		$listing = $definition['termListing'] ?? $definition['term_collection'] ?? [];
		$urls    = $definition['urls'] ?? $definition['routing'] ?? [];

		$relation = array_filter([
			'kind'    => 'classify',
			'from'    => is_string($from) ? [$from] : (is_array($from) ? array_values($from) : []),
			'to'      => [$name],
			'field'   => is_string($field) && $field !== $name ? $field : null,
			'aliases' => is_string($aliases) ? [$aliases] : (is_array($aliases) ? array_values($aliases) : []),
			'create'  => true,
			// Terms have pages by default (one without URLs has none anyway).
			'inverse' => array_filter(['listing' => is_array($listing) ? $listing : []])
		], static fn (mixed $value): bool => $value !== null && $value !== []);

		return [$type, $relation];
	}
}
