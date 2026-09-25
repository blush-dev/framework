<?php

/**
 * Make attribute.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Container\Attributes;

use Attribute;
use Override;
use Blush\Container\Container;

/**
 * Resolves the attributed parameter from the container by identifier,
 * mirroring `Container::make()`, optionally configured with inline
 * constructor overrides:
 *
 *     public function __construct(
 *         #[Make(Cache::class, ['ttl' => 3600])] Cache $cache
 *     ) {}
 *
 * Unlike `Build`, a cached singleton matching the identifier is returned as
 * is when overrides are omitted. Once overrides are given, `make()` and
 * `build()` behave the same: the override is never cached, and any existing
 * cached instance is left untouched. The overrides are attribute arguments,
 * so they are limited to compile-time constants — scalars, arrays, enums,
 * and class constants. A value that must be resolved or computed (another
 * service, a closure) belongs in a contextual binding instead.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Make implements ContextualAttribute
{
	/**
	 * Stores the identifier to resolve and the constructor overrides to pass.
	 *
	 * @param class-string         $abstract
	 * @param array<string, mixed> $parameters
	 */
	public function __construct(
		private readonly string $abstract,
		private readonly array  $parameters = []
	) {}

	/**
	 * Resolves the identifier from the container with the stored overrides.
	 */
	#[Override]
	public function resolve(Container $container): object
	{
		return $container->make($this->abstract, $this->parameters);
	}
}
