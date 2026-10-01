<?php

/**
 * Route.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing;

use NoDiscard;

/**
 * A route definition: which methods and path pattern it answers, and the
 * handler that answers them. Routes are immutable; the fluent methods
 * return changed copies.
 *
 *     Route::get('/about', About::class);
 *     Route::get('/archives/{year:\d{4}}', [Archive::class, 'year'])->named('archive.year');
 *     Route::post('/contact', Contact::class)->middleware(VerifyCsrf::class);
 *
 * **Patterns** are paths with `{name}` placeholders, each matching one
 * segment (`[^/]+`) unless given a constraint inline (`{year:\d{4}}`) or with
 * `where()`. A controller parameter typed `int`, `float`, or a backed enum
 * gets a matching constraint automatically. Constraints may not contain
 * capturing groups. Write paths without a trailing slash; `RouteConfig`'s
 * `trailingSlash` setting decides the canonical form, except for an
 * `exact()` route, which answers its path as written and is never
 * redirected to the other form (for APIs, webhooks, and the admin, which
 * aren't pages people link to).
 *
 * **Handlers** are an invokable class, a `[Class, 'method']` pair, or a
 * PSR-15 `RequestHandlerInterface` class. They're resolved through the
 * container, and their parameters are filled by name from the route's
 * parameters and defaults, with the request passed to any parameter typed
 * as one. Everything else is autowired. A handler returns a response.
 */
final readonly class Route
{
	/**
	 * @param list<string>                             $methods     Uppercase HTTP methods.
	 * @param class-string                             $controller
	 * @param ?string                                  $action      The method to call; `null` picks `__invoke()` or `handle()`.
	 * @param array<string, string>                    $constraints Regexes for parameters, by name.
	 * @param array<string, string|int|float|bool|null> $defaults   Values for handler parameters not in the path.
	 * @param list<class-string>                       $middleware  PSR-15 middleware run for this route, outermost first.
	 * @param bool                                     $exact       Whether it answers its path as written, without the trailing-slash redirect.
	 * @throws InvalidRoute
	 */
	public function __construct(
		public array $methods,
		public string $path,
		public string $controller,
		public ?string $action = null,
		public ?string $name = null,
		public array $constraints = [],
		public array $defaults = [],
		public array $middleware = [],
		public bool $exact = false
	) {
		if ($methods === []) {
			throw new InvalidRoute(sprintf('The route "%s" needs at least one method.', $path));
		}

		foreach ($methods as $method) {
			if (preg_match('/^[A-Z]+$/', $method) !== 1) {
				throw new InvalidRoute(sprintf('The route "%s" has an invalid method "%s"; use uppercase names.', $path, $method));
			}
		}

		if (! str_starts_with($path, '/')) {
			throw new InvalidRoute(sprintf('The route path "%s" must start with "/".', $path));
		}

		if ($name === '') {
			throw new InvalidRoute(sprintf('The route "%s" has an empty name.', $path));
		}
	}

	/**
	 * Creates a route answering `GET` (and so `HEAD`).
	 *
	 * @param class-string|array{0: class-string, 1: string} $handler
	 */
	public static function get(string $path, string|array $handler): self
	{
		return self::match(['GET'], $path, $handler);
	}

	/**
	 * Creates a `POST` route.
	 *
	 * @param class-string|array{0: class-string, 1: string} $handler
	 */
	public static function post(string $path, string|array $handler): self
	{
		return self::match(['POST'], $path, $handler);
	}

	/**
	 * Creates a `PUT` route.
	 *
	 * @param class-string|array{0: class-string, 1: string} $handler
	 */
	public static function put(string $path, string|array $handler): self
	{
		return self::match(['PUT'], $path, $handler);
	}

	/**
	 * Creates a `PATCH` route.
	 *
	 * @param class-string|array{0: class-string, 1: string} $handler
	 */
	public static function patch(string $path, string|array $handler): self
	{
		return self::match(['PATCH'], $path, $handler);
	}

	/**
	 * Creates a `DELETE` route.
	 *
	 * @param class-string|array{0: class-string, 1: string} $handler
	 */
	public static function delete(string $path, string|array $handler): self
	{
		return self::match(['DELETE'], $path, $handler);
	}

	/**
	 * Creates a route answering the given methods.
	 *
	 * @param list<string>                                   $methods
	 * @param class-string|array{0: class-string, 1: string} $handler
	 * @throws InvalidRoute
	 */
	public static function match(array $methods, string $path, string|array $handler): self
	{
		[$controller, $action] = is_array($handler) ? $handler : [$handler, null];

		return new self(
			methods: array_map(strtoupper(...), $methods)
				|> array_unique(...)
				|> array_values(...),
			path: $path,
			controller: $controller,
			action: $action
		);
	}

	/**
	 * Prefixes the path, name, and middleware of each route, so a set of
	 * routes can share them, and makes them all `exact()` when asked.
	 *
	 *     Route::group('/admin', [Route::get('/', Dashboard::class)->named('dashboard')], name: 'admin.', middleware: [Authenticate::class]);
	 *
	 * @param  list<self>         $routes
	 * @param  list<class-string> $middleware Runs outside each route's own middleware.
	 * @return list<self>
	 */
	public static function group(string $prefix, array $routes, string $name = '', array $middleware = [], bool $exact = false): array
	{
		$prefix = rtrim($prefix, '/');

		return array_map(
			static fn (self $route): self => clone($route, [
				'path'       => $prefix === '' ? $route->path : ($route->path === '/' ? $prefix : $prefix . $route->path),
				'name'       => $route->name === null ? null : $name . $route->name,
				'middleware' => [...$middleware, ...$route->middleware],
				'exact'      => $exact || $route->exact
			]),
			$routes
		);
	}

	/**
	 * Rebuilds a route from `toArray()` output (or hand-written arrays in
	 * config).
	 *
	 * @param  array<mixed> $data
	 * @throws InvalidRoute
	 */
	public static function fromArray(array $data): self
	{
		$path = $data['path'] ?? null;

		if (! is_string($path)) {
			throw new InvalidRoute('A route array needs a string "path".');
		}

		$controller = $data['controller'] ?? null;
		$action     = $data['action'] ?? null;
		$name       = $data['name'] ?? null;

		if (! is_string($controller) || ! class_exists($controller)) {
			throw new InvalidRoute(sprintf('The route "%s" needs an existing "controller" class.', $path));
		}

		if (($action !== null && ! is_string($action)) || ($name !== null && ! is_string($name))) {
			throw new InvalidRoute(sprintf('The route "%s" has a non-string "action" or "name".', $path));
		}

		return new self(
			methods: self::stringList($data['methods'] ?? ['GET'], 'methods', $path),
			path: $path,
			controller: $controller,
			action: $action,
			name: $name,
			constraints: self::stringMap($data['constraints'] ?? [], $path),
			defaults: self::scalarMap($data['defaults'] ?? [], $path),
			middleware: self::classList($data['middleware'] ?? [], $path),
			exact: ($data['exact'] ?? false) === true
		);
	}

	/**
	 * Returns a copy with the given name, used to generate its URL.
	 */
	#[NoDiscard]
	public function named(string $name): self
	{
		return new self(...[...$this->toArray(), 'name' => $name]);
	}

	/**
	 * Returns a copy with parameter constraints added, as one name and
	 * regex or a map of them.
	 *
	 * @param string|array<string, string> $name
	 */
	#[NoDiscard]
	public function where(string|array $name, ?string $pattern = null): self
	{
		$constraints = is_array($name) ? $name : [$name => (string) $pattern];

		return clone($this, ['constraints' => [...$this->constraints, ...$constraints]]);
	}

	/**
	 * Returns a copy with default parameter values added.
	 *
	 * @param array<string, string|int|float|bool|null> $defaults
	 */
	#[NoDiscard]
	public function defaults(array $defaults): self
	{
		return clone($this, ['defaults' => [...$this->defaults, ...$defaults]]);
	}

	/**
	 * Returns a copy with middleware appended (run inside any already
	 * set).
	 *
	 * @param class-string ...$middleware
	 */
	#[NoDiscard]
	public function middleware(string ...$middleware): self
	{
		return clone($this, ['middleware' => [...$this->middleware, ...array_values($middleware)]]);
	}

	/**
	 * Returns a copy that answers its path as written: the trailing-slash
	 * setting neither redirects it nor changes the URLs made for it.
	 */
	#[NoDiscard]
	public function exact(bool $exact = true): self
	{
		return clone($this, ['exact' => $exact]);
	}

	/**
	 * Validates a list of strings from array input.
	 *
	 * @return list<string>
	 * @throws InvalidRoute
	 */
	private static function stringList(mixed $value, string $key, string $path): array
	{
		if (! is_array($value) || ! array_is_list($value) || ! array_all($value, static fn (mixed $item): bool => is_string($item))) {
			throw new InvalidRoute(sprintf('The route "%s" needs "%s" to be a list of strings.', $path, $key));
		}

		/** @var list<string> $value */
		return $value;
	}

	/**
	 * Validates a list of class names from array input.
	 *
	 * @return list<class-string>
	 * @throws InvalidRoute
	 */
	private static function classList(mixed $value, string $path): array
	{
		$classes = self::stringList($value, 'middleware', $path);

		foreach ($classes as $class) {
			if (! class_exists($class)) {
				throw new InvalidRoute(sprintf('The route "%s" lists middleware "%s", which does not exist.', $path, $class));
			}
		}

		/** @var list<class-string> $classes */
		return $classes;
	}

	/**
	 * Validates a map of strings from array input.
	 *
	 * @return array<string, string>
	 * @throws InvalidRoute
	 */
	private static function stringMap(mixed $value, string $path): array
	{
		if (! is_array($value) || ! array_all($value, static fn (mixed $item, int|string $key): bool => is_string($key) && is_string($item))) {
			throw new InvalidRoute(sprintf('The route "%s" needs "constraints" to map names to strings.', $path));
		}

		/** @var array<string, string> $value */
		return $value;
	}

	/**
	 * Validates a map of scalars from array input.
	 *
	 * @return array<string, string|int|float|bool|null>
	 * @throws InvalidRoute
	 */
	private static function scalarMap(mixed $value, string $path): array
	{
		if (! is_array($value) || ! array_all($value, static fn (mixed $item, int|string $key): bool => is_string($key) && ($item === null || is_scalar($item)))) {
			throw new InvalidRoute(sprintf('The route "%s" needs "defaults" to map names to scalar values.', $path));
		}

		/** @var array<string, string|int|float|bool|null> $value */
		return $value;
	}

	/**
	 * Describes the handler for listings and errors.
	 */
	public function handlerName(): string
	{
		return $this->action === null ? $this->controller : "{$this->controller}::{$this->action}";
	}

	/**
	 * Returns the route as an array of exportable values (the compiled
	 * config format).
	 *
	 * @return array{
	 *     methods: list<string>,
	 *     path: string,
	 *     controller: class-string,
	 *     action: ?string,
	 *     name: ?string,
	 *     constraints: array<string, string>,
	 *     defaults: array<string, string|int|float|bool|null>,
	 *     middleware: list<class-string>,
	 *     exact: bool
	 * }
	 */
	public function toArray(): array
	{
		return [
			'methods'     => $this->methods,
			'path'        => $this->path,
			'controller'  => $this->controller,
			'action'      => $this->action,
			'name'        => $this->name,
			'constraints' => $this->constraints,
			'defaults'    => $this->defaults,
			'middleware'  => $this->middleware,
			'exact'       => $this->exact
		];
	}
}
