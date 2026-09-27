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

use Blush\Content\Schema\Definition;
use Blush\Content\Schema\InvalidSchema;

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
	 * The prefix, without slashes, or `null` to use the type's folder.
	 */
	public ?string $prefix;

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
	 */
	public function __construct(?string $prefix = null, ?string $single = null, ?string $collection = null, array $paths = [])
	{
		$shortcuts = array_filter(['single' => $single, 'collection' => $collection], static fn (?string $path): bool => $path !== null);

		$this->prefix = $prefix === null ? null : trim($prefix, '/');
		$this->paths  = [
			...self::DEFAULT_PATHS,
			...array_map(static fn (string $path): string => trim($path, '/'), [...$paths, ...$shortcuts])
		];
	}

	/**
	 * Builds URLs from a map of `prefix`, `single`, `collection`, and
	 * `paths`, as data types and 1.x's `routing` write them.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidSchema
	 */
	public static function fromArray(array $data, string $label): self
	{
		$urls  = new Definition($data, $label);
		$paths = $urls->map('paths');

		$unknown = array_diff(array_map(strval(...), array_keys($data)), ['prefix', 'single', 'collection', 'paths']);

		if ($unknown !== []) {
			throw new InvalidSchema(sprintf('%s has unknown options: %s.', $label, implode(', ', $unknown)));
		}

		if (! array_all($paths, static fn (mixed $path, mixed $key): bool => is_string($key) && is_string($path))) {
			throw new InvalidSchema(sprintf('%s "paths" must map route keys to paths.', $label));
		}

		/** @var array<string, string> $paths */
		return new self($urls->nullableString('prefix'), $urls->nullableString('single'), $urls->nullableString('collection'), $paths);
	}

	/**
	 * Returns a route key's path, relative to the prefix.
	 */
	public function path(string $key): ?string
	{
		return $this->paths[$key] ?? null;
	}

	/**
	 * Returns the URLs as an array: the prefix and only the paths that
	 * differ from the defaults.
	 *
	 * @return array{prefix?: string, paths?: array<string, string>}
	 */
	public function toArray(): array
	{
		$paths = array_diff_assoc($this->paths, self::DEFAULT_PATHS);

		return array_filter(['prefix' => $this->prefix, 'paths' => $paths], static fn (mixed $value): bool => $value !== null && $value !== []);
	}
}
