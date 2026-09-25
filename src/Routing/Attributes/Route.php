<?php

/**
 * Route attribute.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing\Attributes;

use Attribute;

/**
 * Declares a route on a controller: on an invokable (or PSR-15 handler)
 * class, or on a public method. It's repeatable, so one handler can answer
 * several paths. `Get`, `Post`, `Put`, `Patch`, and `Delete` are shorthands.
 *
 *     final readonly class Archive
 *     {
 *         #[Get('/archives/{year}', name: 'archive.year')]
 *         public function year(int $year): ResponseInterface
 *     }
 *
 * The controller must be listed in `RouteConfig::$controllers` or tagged
 * with `ControllerRoutes::TAG` by a provider.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
readonly class Route
{
	/**
	 * @param list<string>                              $methods
	 * @param array<string, string>                     $where      Parameter constraints.
	 * @param array<string, string|int|float|bool|null> $defaults
	 * @param list<class-string>                        $middleware
	 */
	public function __construct(
		public string $path,
		public array $methods = ['GET'],
		public ?string $name = null,
		public array $where = [],
		public array $defaults = [],
		public array $middleware = []
	) {}
}
