<?php

/**
 * Route group attribute.
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
 * Prefixes the path, name, and middleware of every route a controller
 * class declares, and makes them all exact when asked (`Route::exact()`).
 *
 *     #[Group('/admin', name: 'admin.', middleware: [Authenticate::class])]
 *     final readonly class Dashboard
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Group
{
	/**
	 * @param list<class-string> $middleware Runs outside each route's own middleware.
	 */
	public function __construct(
		public string $prefix = '',
		public string $name = '',
		public array $middleware = [],
		public bool $exact = false
	) {}
}
