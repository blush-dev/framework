<?php

/**
 * Controller route source.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing\Sources;

use Override;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use Blush\Container\Attributes\TaggedAbstracts;
use Blush\Routing\Attributes\Group;
use Blush\Routing\Attributes\Route as RouteAttribute;
use Blush\Routing\InvalidRoute;
use Blush\Routing\Route;
use Blush\Routing\RouteConfig;
use Blush\Routing\RoutePriority;
use Blush\Routing\RouteSource;

/**
 * Reads routes from controller attributes (`#[Get]`, `#[Route]`, and so
 * on). Controllers come from `RouteConfig::$controllers` (the site) and
 * from classes tagged with `ControllerRoutes::TAG` (extensions). Classes
 * aren't scanned for: listing them keeps compilation cheap and explicit.
 * Themes can't declare routes (D-020).
 */
final readonly class ControllerRoutes implements RouteSource
{
	/**
	 * The container tag for controller classes.
	 */
	public const string TAG = 'routing.controllers';

	/**
	 * @param array<mixed, string> $tagged
	 */
	public function __construct(
		private RouteConfig $config,
		#[TaggedAbstracts(self::TAG)] private array $tagged = []
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function priority(): RoutePriority
	{
		return RoutePriority::Controllers;
	}

	/**
	 * @inheritDoc
	 * @return list<Route>
	 * @throws InvalidRoute
	 */
	#[Override]
	public function routes(): iterable
	{
		$routes = [];

		foreach (array_unique([...array_values($this->tagged), ...$this->config->controllers]) as $class) {
			if (! class_exists($class)) {
				throw new InvalidRoute(sprintf('The controller "%s" does not exist.', $class));
			}

			array_push($routes, ...$this->routesOf(new ReflectionClass($class)));
		}

		return $routes;
	}

	/**
	 * Returns the routes a class declares, on itself and its public
	 * methods, with its group applied.
	 *
	 * @param  ReflectionClass<object> $class
	 * @return list<Route>
	 */
	private function routesOf(ReflectionClass $class): array
	{
		$routes = $this->declared($class, $class->getName(), null);

		foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
			if (! $method->isStatic()) {
				array_push($routes, ...$this->declared($method, $class->getName(), $method->getName()));
			}
		}

		$group = array_first($class->getAttributes(Group::class))?->newInstance();

		return $group === null
			? $routes
			: Route::group($group->prefix, $routes, $group->name, $group->middleware);
	}

	/**
	 * Builds the routes declared on a class or method.
	 *
	 * @param  ReflectionClass<object>|ReflectionMethod $target
	 * @param  class-string                              $controller
	 * @return list<Route>
	 */
	private function declared(ReflectionClass|ReflectionMethod $target, string $controller, ?string $action): array
	{
		return array_map(
			static fn (ReflectionAttribute $attribute): Route => self::route($attribute->newInstance(), $controller, $action),
			$target->getAttributes(RouteAttribute::class, ReflectionAttribute::IS_INSTANCEOF)
		);
	}

	/**
	 * Turns an attribute into a route.
	 *
	 * @param class-string $controller
	 */
	private static function route(RouteAttribute $attribute, string $controller, ?string $action): Route
	{
		return new Route(
			methods: array_map(strtoupper(...), $attribute->methods),
			path: $attribute->path,
			controller: $controller,
			action: $action,
			name: $attribute->name,
			constraints: $attribute->where,
			defaults: $attribute->defaults,
			middleware: $attribute->middleware
		);
	}
}
