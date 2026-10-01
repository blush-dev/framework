<?php

/**
 * Route compiler.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing;

use BackedEnum;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Blush\Container\Attributes\Tagged;
use Blush\Http\Request;

/**
 * Compiles routes and redirects from their sources into a `RouteTable`.
 *
 * - Sources are read in priority order (`RoutePriority`), then
 *   registration order. When two routes claim the same method and pattern,
 *   the first wins and the other is recorded as shadowed.
 * - Each handler is resolved to a class and a public method and checked,
 *   along with its middleware, so a broken route fails at compile time.
 * - Handler parameters are read once: those typed as a request get the
 *   request; path parameters typed `int`, `float`, `bool`, or a backed enum
 *   are cast, and unconstrained ones get a matching constraint.
 * - Route names must be unique.
 *
 * @phpstan-import-type CompiledRouteArray from CompiledRoute
 * @phpstan-import-type RouteTableArray from RouteTable
 * @phpstan-import-type Cast from CompiledRoute
 */
final readonly class RouteCompiler
{
	/**
	 * How many patterns share one combined regex.
	 */
	public const int CHUNK = 32;

	/**
	 * Constraints inferred from a parameter's built-in type.
	 */
	private const array TYPE_CONSTRAINTS = [
		'int'   => '\d+',
		'float' => '\d+(?:\.\d+)?'
	];

	/**
	 * @param list<RouteSource>    $sources
	 * @param list<RedirectSource> $redirects
	 */
	public function __construct(
		#[Tagged(RouteSource::TAG)] private array $sources = [],
		#[Tagged(RedirectSource::TAG)] private array $redirects = []
	) {}

	/**
	 * Compiles the table.
	 *
	 * @throws InvalidRoute
	 */
	public function compile(): RouteTable
	{
		$sources = $this->sources;

		usort($sources, static fn (RouteSource $a, RouteSource $b): int => $a->priority()->value <=> $b->priority()->value);

		$table = [
			'routes'           => [],
			'static'           => [],
			'dynamic'          => [],
			'names'            => [],
			'redirects'        => [],
			'staticRedirects'  => [],
			'dynamicRedirects' => [],
			'shadowed'         => []
		];

		/** @var array<string, list<string>> $patterns Dynamic regex alternatives, by method. */
		$patterns = [];

		/** @var array<string, string> $claimed Handlers by method and pattern, to detect shadowing. */
		$claimed = [];

		foreach ($sources as $source) {
			foreach ($source->routes() as $route) {
				$compiled = $this->compileRoute($route, $source->priority());
				$regex    = $compiled['pattern']['params'] === [] ? null : RoutePattern::fromArray($compiled['pattern'])->regex();
				$key      = $regex ?? preg_quote($compiled['pattern']['path'], '~');
				$methods  = [];

				foreach ($compiled['methods'] as $method) {
					if (isset($claimed["{$method} {$key}"])) {
						$table['shadowed'][] = [
							'method'   => $method,
							'path'     => $route->path,
							'handler'  => $route->handlerName(),
							'priority' => $source->priority()->value,
							'by'       => $claimed["{$method} {$key}"]
						];
						continue;
					}

					$claimed["{$method} {$key}"] = $route->handlerName();
					$methods[] = $method;
				}

				if ($methods === []) {
					continue;
				}

				$index = count($table['routes']);
				$compiled['methods'] = $methods;
				$table['routes'][]   = $compiled;

				if ($compiled['name'] !== null) {
					if (isset($table['names'][$compiled['name']])) {
						throw new InvalidRoute(sprintf('The route name "%s" is used more than once.', $compiled['name']));
					}

					$table['names'][$compiled['name']] = $index;
				}

				foreach ($methods as $method) {
					if ($regex === null) {
						$table['static'][$compiled['pattern']['path']][$method] = $index;
					} else {
						$patterns[$method][] = "{$regex}(*MARK:{$index})";
					}
				}
			}
		}

		$table['dynamic'] = array_map(self::chunks(...), $patterns);

		return new RouteTable($this->compileRedirects($table));
	}

	/**
	 * Adds the redirects to the table. The first redirect for a pattern
	 * wins.
	 *
	 * @param  RouteTableArray $table
	 * @return RouteTableArray
	 * @throws InvalidRoute
	 */
	private function compileRedirects(array $table): array
	{
		$patterns = [];
		$seen     = [];

		foreach ($this->redirects as $source) {
			foreach ($source->redirects() as $redirect) {
				$pattern = RoutePattern::parse(self::trim($redirect->from));
				$key     = $pattern->isStatic() ? $pattern->path : $pattern->regex();

				if (isset($seen[$key])) {
					continue;
				}

				$seen[$key] = true;
				$index      = count($table['redirects']);

				$table['redirects'][] = [
					'pattern' => $pattern->toArray(),
					'to'      => $redirect->to,
					'status'  => $redirect->status->value
				];

				if ($pattern->isStatic()) {
					$table['staticRedirects'][$pattern->path] = $index;
				} else {
					$patterns[] = "{$pattern->regex()}(*MARK:{$index})";
				}
			}
		}

		$table['dynamicRedirects'] = self::chunks($patterns);

		return $table;
	}

	/**
	 * Compiles one route.
	 *
	 * @return CompiledRouteArray
	 * @throws InvalidRoute
	 */
	private function compileRoute(Route $route, RoutePriority $priority): array
	{
		if (! class_exists($route->controller)) {
			throw new InvalidRoute(sprintf('The route "%s" points to "%s", which does not exist.', $route->path, $route->controller));
		}

		foreach ($route->middleware as $middleware) {
			if (! is_subclass_of($middleware, MiddlewareInterface::class)) {
				throw new InvalidRoute(sprintf(
					'The route "%s" lists "%s" as middleware, but it does not implement %s.',
					$route->path,
					$middleware,
					MiddlewareInterface::class
				));
			}
		}

		$method  = $this->action($route);
		$pattern = RoutePattern::parse(self::trim($route->path), $route->constraints);

		/** @var array<string, Cast> $casts */
		$casts         = [];
		$constraints   = [];
		$requestParams = [];

		foreach ($method->getParameters() as $parameter) {
			$type = $parameter->getType();
			$name = $parameter->getName();

			if (! $type instanceof ReflectionNamedType) {
				continue;
			}

			$typeName = $type->getName();

			if (! $type->isBuiltin() && is_a(Request::class, $typeName, true)) {
				$requestParams[] = $name;
				continue;
			}

			if (! in_array($name, $pattern->params, true)) {
				continue;
			}

			if (in_array($typeName, ['int', 'float', 'bool'], true)) {
				$casts[$name] = $typeName;
				$constraints[$name] = self::TYPE_CONSTRAINTS[$typeName] ?? RoutePattern::SEGMENT;
			} elseif (is_subclass_of($typeName, BackedEnum::class)) {
				$casts[$name] = $typeName;
				$constraints[$name] = self::enumConstraint($typeName);
			}
		}

		return [
			'methods'       => $route->methods,
			'pattern'       => $pattern->withDefaultConstraints($constraints)->toArray(),
			'controller'    => $route->controller,
			'action'        => $method->getName(),
			'name'          => $route->name,
			'defaults'      => $route->defaults,
			'middleware'    => $route->middleware,
			'casts'         => $casts,
			'requestParams' => $requestParams,
			'priority'      => $priority->value,
			'exact'         => $route->exact
		];
	}

	/**
	 * Resolves the method a route calls: the named action, or else
	 * `__invoke()`, or `handle()` for a PSR-15 handler.
	 *
	 * @throws InvalidRoute
	 */
	private function action(Route $route): ReflectionMethod
	{
		$class  = new ReflectionClass($route->controller);
		$action = $route->action;

		if ($action === null) {
			$action = match (true) {
				$class->hasMethod('__invoke')                                 => '__invoke',
				$class->implementsInterface(RequestHandlerInterface::class) => 'handle',
				default => throw new InvalidRoute(sprintf(
					'The route "%s" points to %s, which is not invokable or a request handler; name a method.',
					$route->path,
					$route->controller
				))
			};
		}

		if (! $class->hasMethod($action) || ! $class->getMethod($action)->isPublic()) {
			throw new InvalidRoute(sprintf('The route "%s" points to %s::%s(), which is not a public method.', $route->path, $route->controller, $action));
		}

		return $class->getMethod($action);
	}

	/**
	 * Returns a constraint matching a backed enum's values.
	 *
	 * @param class-string<BackedEnum> $enum
	 */
	private static function enumConstraint(string $enum): string
	{
		$values = array_map(
			static fn (BackedEnum $case): string => preg_quote((string) $case->value, '~'),
			$enum::cases()
		);

		return $values === [] ? RoutePattern::SEGMENT : '(?:' . implode('|', $values) . ')';
	}

	/**
	 * Removes a trailing slash: the table is keyed without one, and the
	 * router applies the trailing-slash setting.
	 */
	private static function trim(string $path): string
	{
		$trimmed = rtrim($path, '/');

		return $trimmed === '' ? '/' : $trimmed;
	}

	/**
	 * Joins regex alternatives into anchored, combined regexes of up to
	 * `CHUNK` alternatives each. Branch reset (`(?|`) numbers each
	 * alternative's groups from 1.
	 *
	 * @param  list<string> $alternatives
	 * @return list<string>
	 */
	private static function chunks(array $alternatives): array
	{
		return array_map(
			static fn (array $chunk): string => '~^(?|' . implode('|', $chunk) . ')$~',
			array_chunk($alternatives, self::CHUNK)
		);
	}
}
