<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container;

/**
 * A cache whose TTL is a constructor scalar. The default lets it be bound as a
 * shared instance, while `#[MakeFresh]` can build a fresh one with an
 * overridden TTL.
 */
final class ConfigurableCache implements Cache
{
	public function __construct(public readonly int $ttl = 60)
	{}
}
