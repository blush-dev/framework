<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container;

final class CacheAvailability
{
	public function __construct(private Cache $cache)
	{
	}

	public function cacheIsAvailable(): bool
	{
		return ! $this->cache instanceof NullCache;
	}
}
