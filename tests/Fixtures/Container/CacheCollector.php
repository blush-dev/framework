<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container;

use Blush\Container\Attributes\Tagged;

final class CacheCollector
{
	/** @var array<Cache> */
	public readonly array $caches;

	public function __construct(
		#[Tagged('caches')] Cache ...$caches
	) {
		$this->caches = $caches;
	}
}
