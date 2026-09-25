<?php

/**
 * Build attribute.
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
 * Builds a fresh, unshared service, mirroring `ServiceResolver::build()`. The
 * built instance bypasses any cached singleton — the shared instance (if any)
 * is left in place and a newly built one is injected. Use it to give a single
 * consumer its own private copy of a service, optionally configured with inline
 * constructor overrides:
 *
 *     public function __construct(
 *         #[Build(TransientCache::class, ['ttl' => 3600])] Cache $cache
 *     ) {}
 *
 * The overrides are attribute arguments, so they are limited to compile-time
 * constants — scalars, arrays, enums, and class constants. A value that must be
 * resolved or computed (another service, a closure) belongs in a contextual
 * binding instead.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Build implements ContextualAttribute
{
	/**
	 * Stores the identifier to build and the constructor overrides to pass.
	 *
	 * @param class-string         $abstract
	 * @param array<string, mixed> $parameters
	 */
	public function __construct(
		private readonly string $abstract,
		private readonly array  $parameters = []
	) {}

	/**
	 * Builds a fresh instance of the identifier with the stored overrides.
	 */
	#[Override]
	public function resolve(Container $container): object
	{
		return $container->build($this->abstract, $this->parameters);
	}
}
