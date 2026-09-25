<?php

/**
 * Route config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;

/**
 * The site's routing settings, from `config/routes.php`:
 *
 *     return new RouteConfig(
 *         routes: [Route::get('/contact', Contact::class)->named('contact')],
 *         controllers: [App\Controllers\Archive::class],
 *         redirects: [new Redirect('/blog/{slug}', '/archives/{slug}')]
 *     );
 *
 * Extensions add routes through their providers instead (a tagged
 * `RouteSource` or controller class).
 */
final readonly class RouteConfig implements Config
{
	/**
	 * @param list<Route>        $routes        Routes, at `RoutePriority::Config`.
	 * @param list<class-string> $controllers   Classes whose routing attributes declare routes.
	 * @param list<Redirect>     $redirects     Redirects, consulted before a 404.
	 * @param bool               $trailingSlash Whether canonical URLs end in `/`. The other form
	 *        redirects to the canonical one, and generated URLs follow it. Paths whose last
	 *        segment has a dot (`/feed.xml`) never get one.
	 * @throws InvalidConfig
	 */
	public function __construct(
		public array $routes = [],
		public array $controllers = [],
		public array $redirects = [],
		public bool $trailingSlash = false
	) {
		foreach ($controllers as $class) {
			if (! class_exists($class)) {
				throw new InvalidConfig(sprintf('RouteConfig "controllers" lists "%s", which does not exist.', $class));
			}
		}
	}

	/**
	 * Returns the canonical form of a path under the trailing-slash
	 * setting. `/` is always canonical.
	 */
	public function canonicalPath(string $path): string
	{
		$trimmed = rtrim($path, '/');

		if ($trimmed === '') {
			return '/';
		}

		$last = substr($trimmed, (int) strrpos($trimmed, '/') + 1);

		return $this->trailingSlash && ! str_contains($last, '.') ? "{$trimmed}/" : $trimmed;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['routes', 'controllers', 'redirects', 'trailingSlash']);

		/** @var list<class-string> $controllers Validated by the constructor. */
		$controllers = $values->stringList('controllers');

		try {
			return new static(
				routes: array_map(Route::fromArray(...), self::arrays($data, 'routes')),
				controllers: $controllers,
				redirects: array_map(Redirect::fromArray(...), self::arrays($data, 'redirects')),
				trailingSlash: $values->bool('trailingSlash', false)
			);
		} catch (InvalidRoute $e) {
			throw new InvalidConfig(sprintf('RouteConfig is invalid: %s', $e->getMessage()), previous: $e);
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'routes'        => array_map(static fn (Route $route): array => $route->toArray(), $this->routes),
			'controllers'   => $this->controllers,
			'redirects'     => array_map(static fn (Redirect $redirect): array => $redirect->toArray(), $this->redirects),
			'trailingSlash' => $this->trailingSlash
		];
	}

	/**
	 * Returns a list of arrays from the data.
	 *
	 * @param  array<array-key, mixed> $data
	 * @return list<array<mixed>>
	 * @throws InvalidConfig
	 */
	private static function arrays(array $data, string $key): array
	{
		$value = $data[$key] ?? [];

		if (! is_array($value) || ! array_is_list($value) || ! array_all($value, static fn (mixed $item): bool => is_array($item))) {
			throw new InvalidConfig(sprintf('RouteConfig "%s" must be a list of arrays.', $key));
		}

		/** @var list<array<mixed>> $value */
		return $value;
	}
}
