<?php

/**
 * URL generator.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing;

use BackedEnum;
use Stringable;
use Blush\Core\AppConfig;

/**
 * Builds URLs from route names, so links never hard-code paths:
 *
 *     $urls->to('archive.year', ['year' => 2024]);             // /archives/2024
 *     $urls->to('archive.year', ['year' => 2024, 'page' => 2]); // /archives/2024?page=2
 *     $urls->to('contact', absolute: true);                    // https://example.com/contact
 *
 * Values fill the route's parameters (percent-encoded and checked against
 * their constraints); the rest become the query string, and `null` values
 * are dropped. Paths follow the trailing-slash setting. Absolute URLs use
 * the scheme, host, and port of `AppConfig::$url`.
 *
 * @phpstan-type Value string|int|float|bool|BackedEnum|Stringable|null
 */
final readonly class UrlGenerator
{
	public function __construct(
		private RouteTable $routes,
		private RouteConfig $config,
		private AppConfig $app
	) {}

	/**
	 * Returns the URL for a named route.
	 *
	 * @param  array<string, Value> $params
	 * @throws UrlGenerationException
	 */
	public function to(string $name, array $params = [], bool $absolute = false): string
	{
		$route = $this->routes->named($name)
			?? throw new UrlGenerationException(sprintf('There is no route named "%s".', $name));

		$values = array_map(self::stringify(...), array_filter($params, static fn (mixed $value): bool => $value !== null));
		$path   = $this->config->canonicalPath($route->pattern->build($values));
		$query  = array_diff_key($values, array_flip($route->pattern->params));

		if ($query !== []) {
			$path .= '?' . http_build_query($query, encoding_type: PHP_QUERY_RFC3986);
		}

		return $absolute ? $this->absolute($path) : $path;
	}

	/**
	 * Whether a route has the name.
	 */
	public function has(string $name): bool
	{
		return $this->routes->named($name) !== null;
	}

	/**
	 * Makes a path absolute with the site's origin.
	 */
	public function absolute(string $path): string
	{
		return $this->app->absoluteUrl($path);
	}

	/**
	 * Turns a parameter value into text.
	 *
	 * @param Value $value
	 */
	private static function stringify(mixed $value): string
	{
		return match (true) {
			$value instanceof BackedEnum => (string) $value->value,
			is_bool($value)              => $value ? '1' : '0',
			default                      => (string) $value
		};
	}
}
