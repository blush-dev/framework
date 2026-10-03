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
 *   date's parts (`{year}` … `{second}`) and a taxonomy's name (or the
 *   profiles type's) for the entry's first term of it.
 * - A taxonomy's `single` keys, and the profiles type's, hold `{name}`.
 * - Date archives hold the date's parts down to their level.
 * - A people field's `{field}.single` keys hold `{profile}` (D-351).
 * - `.paged` keys add `{page}`.
 */
final readonly class TypeRouteKeys
{
	/**
	 * Returns the route keys a type answers at, in the order the admin
	 * lists them: its listing, date archives, entries, feeds, then each
	 * people field's archives. `$home` drops the listing (the homepage
	 * is it), `$feeds` are the feed formats' route suffixes (`''`,
	 * `.atom`, `.json`), and `$profiles` says whether the site has a
	 * profiles type, without which there are no people archives. The
	 * profiles type answers only at its profiles' pages and feeds.
	 *
	 * @param  list<string> $feeds
	 * @return list<string>
	 */
	public static function keys(ContentType $type, bool $home, array $feeds, bool $profiles): array
	{
		if (! $type->hasUrls()) {
			return [];
		}

		if ($type instanceof Profiles) {
			return ['single', 'single.paged', ...array_map(static fn (string $suffix): string => "single.feed{$suffix}", $type->hasFeed() ? $feeds : [])];
		}

		$keys     = $home ? [] : ['collection', 'collection.paged'];
		$taxonomy = $type instanceof Taxonomy;

		foreach ($type->dateArchives->levels() as $level) {
			array_push($keys, "collection.{$level->value}", "collection.{$level->value}.paged");
		}

		array_push($keys, 'single', ...($taxonomy ? ['single.paged'] : []));

		if ($type->hasFeed()) {
			foreach ($feeds as $suffix) {
				array_push($keys, "collection.feed{$suffix}", ...($taxonomy ? ["single.feed{$suffix}"] : []));
			}
		}

		foreach ($profiles ? $type->archivedPeople() : [] as $field) {
			array_push($keys, "{$field->field}.collection", "{$field->field}.single", "{$field->field}.single.paged");

			foreach ($type->hasFeed() ? $feeds : [] as $suffix) {
				$keys[] = "{$field->field}.single.feed{$suffix}";
			}
		}

		return $keys;
	}

	/**
	 * Returns the placeholders a key's path must hold, and those it may.
	 * `$taxonomies` are the names of the site's term types (its
	 * taxonomies and authors type), which a collection's `single` may
	 * hold.
	 *
	 * @param  list<string> $taxonomies
	 * @return array{required: list<string>, optional: list<string>}
	 */
	public static function params(ContentType $type, string $key, array $taxonomies): array
	{
		$page     = str_ends_with($key, '.paged') ? ['page'] : [];
		$base     = $page === [] ? $key : substr($key, 0, -strlen('.paged'));
		$dates    = array_map(static fn (DateArchives $level): string => $level->value, DateArchives::Second->levels());
		$required = [];
		$optional = [];

		$people = array_find($type->people, static fn (PeopleField $field): bool => str_starts_with($base, "{$field->field}.single"));

		if ($people !== null) {
			$required = ['profile'];
		} elseif ($base === 'single' && ! $type instanceof Taxonomy && ! $type instanceof Profiles) {
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
	 * @throws InvalidContentType
	 */
	public static function check(ContentType $type, string $key, string $path, array $taxonomies, string $label): void
	{
		if (preg_match('#^[A-Za-z0-9._~/{}-]*$#', $path) !== 1) {
			throw new InvalidContentType(sprintf('The %s address can use letters, digits, "-", "_", ".", "/", and {placeholders}; "%s" has something else.', $label, $path));
		}

		try {
			$held = RoutePattern::parse("/{$path}")->params;
		} catch (InvalidRoute $e) {
			throw new InvalidContentType(sprintf('The %s address "%s" isn\'t one: %s', $label, $path, $e->getMessage()), previous: $e);
		}

		['required' => $required, 'optional' => $optional] = self::params($type, $key, $taxonomies);

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
