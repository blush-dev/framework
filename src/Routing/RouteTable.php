<?php

/**
 * Route table.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing;

use Blush\Http\Status;

/**
 * The compiled route table: every route and redirect, laid out for fast
 * lookup. `RouteCompiler` builds it, and `RouteCache` exports it to
 * `storage/cache/routes.php`, so outside development a request loads it
 * from opcache instead of reading attributes and parsing patterns.
 *
 * Lookup is a hash map for static paths, then a few combined regexes per
 * method for patterns: each chunk is one alternation of up to
 * `RouteCompiler::CHUNK` routes, and `(*MARK)` names the route that
 * matched. Routes stay arrays until one is matched.
 *
 * @phpstan-import-type CompiledRouteArray from CompiledRoute
 * @phpstan-type RedirectArray array{
 *     pattern: array{path: string, parts: list<string|array{0: string, 1: string}>, params: list<string>},
 *     to: string,
 *     status: int
 * }
 * @phpstan-type ShadowedArray array{method: string, path: string, handler: string, priority: int, by: string}
 * @phpstan-type RouteTableArray array{
 *     routes: list<CompiledRouteArray>,
 *     static: array<string, array<string, int>>,
 *     dynamic: array<string, list<string>>,
 *     names: array<string, int>,
 *     redirects: list<RedirectArray>,
 *     staticRedirects: array<string, int>,
 *     dynamicRedirects: list<string>,
 *     shadowed: list<ShadowedArray>
 * }
 */
final readonly class RouteTable
{
	/**
	 * @param RouteTableArray $data
	 */
	public function __construct(private array $data)
	{}

	/**
	 * Finds the route for a method and raw (still percent-encoded) path.
	 * `HEAD` isn't special here; the router falls back to `GET`.
	 */
	public function find(string $method, string $path): ?RouteMatch
	{
		$index = $this->data['static'][$path][$method] ?? null;

		if ($index !== null) {
			return new RouteMatch($this->route($index));
		}

		foreach ($this->data['dynamic'][$method] ?? [] as $regex) {
			$matches = [];

			if (preg_match($regex, $path, $matches) === 1 && isset($matches['MARK'])) {
				$route = $this->route((int) $matches['MARK']);

				return new RouteMatch($route, $this->params($route->pattern, $matches));
			}
		}

		return null;
	}

	/**
	 * Returns the methods some route answers for a path, in table order.
	 * With `$fallbacks` off, methods only a fallback route (such as the
	 * page catch-all) answers are left out.
	 *
	 * @return list<string>
	 */
	public function methodsFor(string $path, bool $fallbacks = true): array
	{
		$methods = [];

		foreach ([...array_keys($this->data['static'][$path] ?? []), ...array_keys($this->data['dynamic'])] as $method) {
			$method = (string) $method;

			if (in_array($method, $methods, true)) {
				continue;
			}

			$match = $this->find($method, $path);

			if ($match !== null && ($fallbacks || $match->route->priority !== RoutePriority::Fallback)) {
				$methods[] = $method;
			}
		}

		return $methods;
	}

	/**
	 * Finds the redirect for a raw path, returned with its `to` resolved:
	 * placeholders filled from the path.
	 */
	public function redirectFor(string $path): ?Redirect
	{
		$index   = $this->data['staticRedirects'][$path] ?? null;
		$matches = [];

		if ($index === null) {
			foreach ($this->data['dynamicRedirects'] as $regex) {
				if (preg_match($regex, $path, $matches) === 1 && isset($matches['MARK'])) {
					$index = (int) $matches['MARK'];
					break;
				}
			}
		}

		if ($index === null) {
			return null;
		}

		$redirect = $this->data['redirects'][$index];
		$pattern  = RoutePattern::fromArray($redirect['pattern']);
		$to       = $redirect['to'];

		foreach ($pattern->params as $position => $name) {
			$to = str_replace('{' . $name . '}', (string) ($matches[$position + 1] ?? ''), $to);
		}

		return new Redirect($path, $to, Status::from($redirect['status']));
	}

	/**
	 * Returns the route with a name.
	 */
	public function named(string $name): ?CompiledRoute
	{
		$index = $this->data['names'][$name] ?? null;

		return $index === null ? null : $this->route($index);
	}

	/**
	 * Returns every route, in precedence order.
	 *
	 * @return list<CompiledRoute>
	 */
	public function routes(): array
	{
		return array_map(CompiledRoute::fromArray(...), $this->data['routes']);
	}

	/**
	 * Returns every redirect as defined (unresolved), in precedence order.
	 *
	 * @return list<Redirect>
	 */
	public function redirects(): array
	{
		return array_map(
			static fn (array $redirect): Redirect => new Redirect($redirect['pattern']['path'], $redirect['to'], Status::from($redirect['status'])),
			$this->data['redirects']
		);
	}

	/**
	 * Returns the method and path pairs that lost to a higher-priority
	 * route, for `routes:list` to report.
	 *
	 * @return list<ShadowedArray>
	 */
	public function shadowed(): array
	{
		return $this->data['shadowed'];
	}

	/**
	 * Returns every controller class, so the container can plan them
	 * ahead of time (D-066).
	 *
	 * @return list<class-string>
	 */
	public function controllers(): array
	{
		return array_values(array_unique(array_column($this->data['routes'], 'controller')));
	}

	/**
	 * Returns the table as exportable values.
	 *
	 * @return RouteTableArray
	 */
	public function toArray(): array
	{
		return $this->data;
	}

	/**
	 * Hydrates a route by index.
	 */
	private function route(int $index): CompiledRoute
	{
		return CompiledRoute::fromArray($this->data['routes'][$index]);
	}

	/**
	 * Returns the percent-decoded parameter values from a regex match.
	 *
	 * @param  array<int|string, string> $matches
	 * @return array<string, string>
	 */
	private function params(RoutePattern $pattern, array $matches): array
	{
		$params = [];

		foreach ($pattern->params as $position => $name) {
			$params[$name] = rawurldecode($matches[$position + 1] ?? '');
		}

		return $params;
	}
}
