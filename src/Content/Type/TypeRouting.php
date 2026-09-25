<?php

/**
 * Content type routing.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

/**
 * How a content type's URLs are built: a prefix (the type's path when
 * unset) and a path pattern per route key, relative to the prefix. Paths
 * default to 1.x's (D-078); a type overrides only the keys it needs:
 *
 *     new TypeRouting(prefix: 'archives', paths: ['single' => '{year}/{month}/{day}/{name}'])
 *
 * Routes are named `{type}.{key}`, such as `post.single`. The feed keys
 * (`collection.feed`, `.feed.atom`, `.feed.json`, and the `single.feed`
 * ones for a taxonomy's terms) are used when the type has a feed.
 */
final readonly class TypeRouting
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
	 * The prefix, without slashes, or `null` to use the type's path.
	 */
	public ?string $prefix;

	/**
	 * Every route key's path, defaults included.
	 *
	 * @var array<string, string>
	 */
	public array $paths;

	/**
	 * @param array<string, string> $paths Paths that replace or add to the defaults.
	 */
	public function __construct(?string $prefix = null, array $paths = [])
	{
		$this->prefix = $prefix === null ? null : trim($prefix, '/');
		$this->paths  = [
			...self::DEFAULT_PATHS,
			...array_map(static fn (string $path): string => trim($path, '/'), $paths)
		];
	}

	/**
	 * Returns a route key's path, relative to the prefix.
	 */
	public function path(string $key): ?string
	{
		return $this->paths[$key] ?? null;
	}

	/**
	 * Returns the routing as an array: the prefix and only the paths that
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
