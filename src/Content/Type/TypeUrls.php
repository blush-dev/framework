<?php

/**
 * Content type URLs.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use Blush\Field\Definition;
use Blush\Field\InvalidSchema;

/**
 * How a content type's URLs are built: a prefix (the type's folder when
 * unset) and a path pattern per route key, relative to the prefix. Paths
 * default to 1.x's (D-078); a type overrides only the keys it needs, with
 * `single` and `collection` as shortcuts for the two most common:
 *
 *     new TypeUrls(prefix: 'archives', single: '{year}/{month}/{day}/{name}')
 *
 * Routes are named `{type}.{key}`, such as `post.single`. The feed keys
 * (`collection.feed`, `.feed.atom`, `.feed.json`, and the `single.feed`
 * ones for a taxonomy's terms) are used when the type has a feed.
 *
 * `authors` is the word a type that credits authors puts its author
 * archives under (D-329), `false` for none: `authors.collection` lists
 * the authors (`{prefix}/authors`), and `authors.single` is one
 * author's archive (`{prefix}/authors/{author}`), with `.paged` and the
 * feed keys. `paths` can still move any of them.
 */
final readonly class TypeUrls
{
	/**
	 * The default path for each route key.
	 *
	 * @var array<string, string>
	 */
	public const array DEFAULT_PATHS = [
		'collection'              => '',
		'single'                  => '{name}',
		'single.paged'            => '{name}/page/{page}',
		'single.feed.json'        => '{name}/feed/json',
		'single.feed.atom'        => '{name}/feed/atom',
		'single.feed'             => '{name}/feed',
		'collection.feed.json'    => 'feed/json',
		'collection.feed.atom'    => 'feed/atom',
		'collection.feed'         => 'feed',
		'collection.paged'        => 'page/{page}',
		'collection.second.paged' => '{year}/{month}/{day}/{hour}/{minute}/{second}/page/{page}',
		'collection.second'       => '{year}/{month}/{day}/{hour}/{minute}/{second}',
		'collection.minute.paged' => '{year}/{month}/{day}/{hour}/{minute}/page/{page}',
		'collection.minute'       => '{year}/{month}/{day}/{hour}/{minute}',
		'collection.hour.paged'   => '{year}/{month}/{day}/{hour}/page/{page}',
		'collection.hour'         => '{year}/{month}/{day}/{hour}',
		'collection.day.paged'    => '{year}/{month}/{day}/page/{page}',
		'collection.day'          => '{year}/{month}/{day}',
		'collection.month.paged'  => '{year}/{month}/page/{page}',
		'collection.month'        => '{year}/{month}',
		'collection.year.paged'   => '{year}/page/{page}',
		'collection.year'         => '{year}'
	];

	/**
	 * The default word author archives sit under.
	 */
	public const string AUTHORS = 'authors';

	/**
	 * The prefix, without slashes, or `null` to use the type's folder.
	 */
	public ?string $prefix;

	/**
	 * The word author archives sit under, without slashes, or `false`
	 * for none.
	 */
	public string|false $authors;

	/**
	 * Every route key's path, defaults included.
	 *
	 * @var array<string, string>
	 */
	public array $paths;

	/**
	 * @param ?string               $prefix     The URL prefix; defaults to the type's folder.
	 * @param ?string               $single     The `single` path, such as `{year}/{name}`.
	 * @param ?string               $collection The `collection` path.
	 * @param array<string, string> $paths      Paths for any route key, replacing or adding to the defaults.
	 * @param string|false          $authors    The word author archives sit under, or `false` for none.
	 * @throws InvalidSchema When `authors` is empty.
	 */
	public function __construct(?string $prefix = null, ?string $single = null, ?string $collection = null, array $paths = [], string|false $authors = self::AUTHORS)
	{
		$shortcuts = array_filter(['single' => $single, 'collection' => $collection], static fn (?string $path): bool => $path !== null);

		if ($authors !== false && trim($authors, '/') === '') {
			throw new InvalidSchema('URLs "authors" must be a word, such as "authors", or false.');
		}

		$this->prefix  = $prefix === null ? null : trim($prefix, '/');
		$this->authors = $authors === false ? false : trim($authors, '/');
		$this->paths   = [
			...self::DEFAULT_PATHS,
			...self::authorPaths($this->authors),
			...array_map(static fn (string $path): string => trim($path, '/'), [...$paths, ...$shortcuts])
		];
	}

	/**
	 * Returns the author archives' paths under a word.
	 *
	 * @return array<string, string>
	 */
	public static function authorPaths(string|false $word): array
	{
		return $word === false ? [] : [
			'authors.collection'       => $word,
			'authors.single'           => "{$word}/{author}",
			'authors.single.paged'     => "{$word}/{author}/page/{page}",
			'authors.single.feed.json' => "{$word}/{author}/feed/json",
			'authors.single.feed.atom' => "{$word}/{author}/feed/atom",
			'authors.single.feed'      => "{$word}/{author}/feed"
		];
	}

	/**
	 * Builds URLs from a map of `prefix`, `single`, `collection`,
	 * `paths`, and `authors` (a word or `false`), as data types and 1.x's
	 * `routing` write them.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidSchema
	 */
	public static function fromArray(array $data, string $label): self
	{
		$urls  = new Definition($data, $label);
		$paths = $urls->map('paths');

		$unknown = array_diff(array_map(strval(...), array_keys($data)), ['prefix', 'single', 'collection', 'paths', 'authors']);

		if ($unknown !== []) {
			throw new InvalidSchema(sprintf('%s has unknown options: %s.', $label, implode(', ', $unknown)));
		}

		if (! array_all($paths, static fn (mixed $path, mixed $key): bool => is_string($key) && is_string($path))) {
			throw new InvalidSchema(sprintf('%s "paths" must map route keys to paths.', $label));
		}

		$authors = $data['authors'] ?? self::AUTHORS;

		if ($authors !== false && ! is_string($authors)) {
			throw new InvalidSchema(sprintf('%s "authors" must be a word, such as "authors", or false.', $label));
		}

		/** @var array<string, string> $paths */
		return new self($urls->nullableString('prefix'), $urls->nullableString('single'), $urls->nullableString('collection'), $paths, $authors);
	}

	/**
	 * Returns a route key's path, relative to the prefix.
	 */
	public function path(string $key): ?string
	{
		return $this->paths[$key] ?? null;
	}

	/**
	 * Returns the URLs as an array: the prefix, the author word when it
	 * isn't the default, and only the paths that differ from the
	 * defaults.
	 *
	 * @return array{prefix?: string, paths?: array<string, string>, authors?: string|false}
	 */
	public function toArray(): array
	{
		$paths = array_diff_assoc($this->paths, [...self::DEFAULT_PATHS, ...self::authorPaths($this->authors)]);

		return array_filter([
			'prefix'  => $this->prefix,
			'paths'   => $paths,
			'authors' => $this->authors === self::AUTHORS ? null : $this->authors
		], static fn (mixed $value): bool => $value !== null && $value !== []);
	}
}
