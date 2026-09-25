<?php

/**
 * Defer attribute.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Container\Attributes;

use Attribute;
use Closure;
use Override;
use Blush\Container\Container;

/**
 * Defers resolution of a service, mirroring `Container::defer()`. Instead of
 * building the service when the attributed class is constructed, it injects a
 * closure that resolves the service when called. Pair it with a `Closure`
 * parameter type:
 *
 *     public function __construct(
 *         #[Defer(Report::class)] Closure $makeReport
 *     ) {}
 *
 * Calling the closure resolves the service from the container, optionally
 * passing build parameters. Whether each call yields a fresh or shared instance
 * follows the binding's lifetime, exactly as `Container::defer()` does.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Defer implements ContextualAttribute
{
	/**
	 * Stores the identifier whose resolution is deferred.
	 */
	public function __construct(private readonly string $abstract)
	{
	}

	/**
	 * Resolves to a closure that defers resolution of the identifier.
	 */
	#[Override]
	public function resolve(Container $container): Closure
	{
		return $container->defer($this->abstract);
	}
}
