<?php

/**
 * Compiled route.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing;

use BackedEnum;

/**
 * A route as the compiler leaves it: its pattern parsed, its handler
 * resolved to a class and method, and everything the dispatcher needs to
 * call it recorded, so no reflection happens per request. It exports to
 * the route cache.
 *
 * @phpstan-type Cast 'int'|'float'|'bool'|class-string<BackedEnum>
 * @phpstan-type CompiledRouteArray array{
 *     methods: list<string>,
 *     pattern: array{path: string, parts: list<string|array{0: string, 1: string}>, params: list<string>},
 *     controller: class-string,
 *     action: string,
 *     name: ?string,
 *     defaults: array<string, string|int|float|bool|null>,
 *     middleware: list<class-string>,
 *     casts: array<string, Cast>,
 *     requestParams: list<string>,
 *     priority: int
 * }
 */
final readonly class CompiledRoute
{
	/**
	 * @param list<string>                              $methods
	 * @param class-string                              $controller
	 * @param array<string, string|int|float|bool|null> $defaults
	 * @param list<class-string>                        $middleware
	 * @param array<string, Cast>                       $casts         How to cast path parameters, by name.
	 * @param list<string>                              $requestParams Handler parameters that take the request.
	 */
	public function __construct(
		public array $methods,
		public RoutePattern $pattern,
		public string $controller,
		public string $action,
		public ?string $name,
		public array $defaults,
		public array $middleware,
		public array $casts,
		public array $requestParams,
		public RoutePriority $priority
	) {}

	/**
	 * Rebuilds a route from `toArray()` output.
	 *
	 * @param CompiledRouteArray $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			methods: $data['methods'],
			pattern: RoutePattern::fromArray($data['pattern']),
			controller: $data['controller'],
			action: $data['action'],
			name: $data['name'],
			defaults: $data['defaults'],
			middleware: $data['middleware'],
			casts: $data['casts'],
			requestParams: $data['requestParams'],
			priority: RoutePriority::from($data['priority'])
		);
	}

	/**
	 * The route's path pattern.
	 */
	public function path(): string
	{
		return $this->pattern->path;
	}

	/**
	 * Describes the handler for listings and errors.
	 */
	public function handlerName(): string
	{
		return $this->action === '__invoke' ? $this->controller : "{$this->controller}::{$this->action}";
	}

	/**
	 * Returns the route as exportable values.
	 *
	 * @return CompiledRouteArray
	 */
	public function toArray(): array
	{
		return [
			'methods'       => $this->methods,
			'pattern'       => $this->pattern->toArray(),
			'controller'    => $this->controller,
			'action'        => $this->action,
			'name'          => $this->name,
			'defaults'      => $this->defaults,
			'middleware'    => $this->middleware,
			'casts'         => $this->casts,
			'requestParams' => $this->requestParams,
			'priority'      => $this->priority->value
		];
	}
}
