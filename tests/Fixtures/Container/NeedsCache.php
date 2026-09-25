<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container;

/**
 * A consumer of the `Cache` interface, used to verify a type-based contextual
 * binding swaps the implementation for this consumer only.
 */
final class NeedsCache
{
	public function __construct(public readonly Cache $cache)
	{}
}
