<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container;

use Blush\Container\Attributes\Build;

/**
 * Configures its `Cache` dependency inline with a `#[MakeFresh]` override.
 */
final class MakeFreshConsumer
{
	public function __construct(
		#[Build(ConfigurableCache::class, ['ttl' => 3600])]
		public readonly Cache $cache
	) {}
}
