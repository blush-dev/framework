<?php

/**
 * Content type route keys.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use Blush\Routing\InvalidRoute;
use Blush\Routing\RoutePattern;

/**
 * The route keys a content type answers at, and what each key's path
 * holds (D-350), so the admin can list a type's addresses and check one
 * before it's saved. A key's path is relative to the type's prefix
 * (`TypeUrls`), and its placeholders are filled as `ContentUrls` fills
 * them:
 *
 * - `single` holds `{name}`, and a collection's may add the published
 *   date's parts (`{year}` … `{second}`) and a term type's name (or the
 *   profiles type's) for the entry's first term of it.
 * - A term type's `single` keys (one a classify relation files entries
 *   under, D-593), and the profiles type's, hold `{name}`.
 * - Date archives hold the date's parts down to their level.
 * - A relation archive's `{relation}.single` keys hold `{target}` (D-596),
 *   a credit's included (D-602).
 * - `.paged` keys add `{page}`.
 */
final readonly class TypeRouteKeys
{
	/**
	 * Returns the route keys a type answers at, in the order the admin
	 * lists them: its listing, date archives, entries, feeds, then each
	 * relation's archives. `$home` drops the listing (the homepage
	 * is it), `$feeds` are the feed formats' route suffixes (`''`,
	 * `.atom`, `.json`), and `$terms` whether the type's entries are terms with pages of their
	 * own (`ContentTypes::hasTermPages()`), paged and with feeds. The
	 * profiles type answers only at its profiles' pages and feeds.
	 * `$relations` names the relations with archives under the type
	 * (`ContentTypes::relationArchives()`), whose keys come last.
	 *
	 * @param  list<string> $feeds
	 * @param  list<string> $relations
	 * @return list<string>
	 */
	public static function keys(ContentType $type, bool $home, array $feeds, bool $terms = false, array $relations = []): array
	{
		if (! $type->hasUrls()) {
			return [];
		}

		if ($type instanceof Profiles) {
			return ['single', 'single.paged', ...array_map(static fn (string $suffix): string => "single.feed{$suffix}", $type->hasFeed() ? $feeds : [])];
		}

		$keys     = $home ? [] : ['collection', 'collection.paged'];
		$taxonomy = $terms;

		foreach ($type->dateArchives->levels() as $level) {
			array_push($keys, "collection.{$level->value}", "collection.{$level->value}.paged");
		}

		array_push($keys, 'single', ...($taxonomy ? ['single.paged'] : []));

		if ($type->hasFeed()) {
			foreach ($feeds as $suffix) {
				array_push($keys, "collection.feed{$suffix}", ...($taxonomy ? ["single.feed{$suffix}"] : []));
			}
		}

		foreach ($relations as $relation) {
			array_push($keys, "{$relation}.collection", "{$relation}.single", "{$relation}.single.paged");

			foreach ($type->hasFeed() ? $feeds : [] as $suffix) {
				$keys[] = "{$relation}.single.feed{$suffix}";
			}
		}

		return $keys;
	}

	/**
	 * Returns the placeholders a key's path must hold, and those it may.
	 * `$taxonomies` are the names of the site's term types (what classify
	 * relations file entries under, and the profiles type), which a
	 * collection's `single` may hold; a term type's own `single` holds
	 * only `{name}`. `$relations` names the type's relation archives.
	 *
	 * @param  list<string> $taxonomies
	 * @param  list<string> $relations
	 * @return array{required: list<string>, optional: list<string>}
	 */
	public static function params(ContentType $type, string $key, array $taxonomies, array $relations = []): array
	{
		$page     = str_ends_with($key, '.paged') ? ['page'] : [];
		$base     = $page === [] ? $key : substr($key, 0, -strlen('.paged'));
		$dates    = array_map(static fn (DateArchives $level): string => $level->value, DateArchives::Second->levels());
		$required = [];
		$optional = [];

		$relation = array_find($relations, static fn (string $relation): bool => str_starts_with($base, "{$relation}.single"));

		if ($relation !== null) {
			$required = ['target'];
		} elseif ($base === 'single' && ! in_array($type->name, $taxonomies, true)) {
			$required = ['name'];
			$optional = [...$dates, ...$taxonomies];
		} elseif (str_starts_with($base, 'single')) {
			$required = ['name'];
		} elseif (preg_match('/^collection\.(year|month|day|hour|minute|second)$/', $base, $match) === 1) {
			$required = array_slice($dates, 0, (int) array_search($match[1], $dates, true) + 1);
		}

		return ['required' => [...$required, ...$page], 'optional' => $optional];
	}

	/**
	 * Checks a path for a key, as the admin writes one: plain text and
	 * placeholders, holding what the key needs and nothing it can't fill.
	 * `$label` names the address in the message.
	 *
	 * @param  list<string> $taxonomies
	 * @param  list<string> $relations
	 * @throws InvalidContentType
	 */
	public static function check(ContentType $type, string $key, string $path, array $taxonomies, string $label, array $relations = []): void
	{
		if (preg_match('#^[A-Za-z0-9._~/{}-]*$#', $path) !== 1) {
			throw new InvalidContentType(sprintf('The %s address can use letters, digits, "-", "_", ".", "/", and {placeholders}; "%s" has something else.', $label, $path));
		}

		try {
			$held = RoutePattern::parse("/{$path}")->params;
		} catch (InvalidRoute $e) {
			throw new InvalidContentType(sprintf('The %s address "%s" isn\'t one: %s', $label, $path, $e->getMessage()), previous: $e);
		}

		['required' => $required, 'optional' => $optional] = self::params($type, $key, $taxonomies, $relations);

		$missing = array_diff($required, $held);
		$unknown = array_diff($held, $required, $optional);

		if ($missing !== []) {
			throw new InvalidContentType(sprintf('The %s address needs %s.', $label, self::braced($missing)));
		}

		if ($unknown !== []) {
			throw new InvalidContentType(sprintf(
				'The %s address can\'t fill %s; it can hold %s.',
				$label,
				self::braced($unknown),
				self::braced([...$required, ...$optional]) ?: 'no placeholders'
			));
		}
	}

	/**
	 * Placeholder names as `{a}, {b}`.
	 *
	 * @param array<array-key, string> $names
	 */
	private static function braced(array $names): string
	{
		return implode(', ', array_map(static fn (string $name): string => "{{$name}}", $names));
	}
}
