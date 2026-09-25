<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container\Plan;

use Blush\Container\Attributes\Singleton;
use Blush\Container\Attributes\Tag;
use Blush\Tests\Fixtures\Container\Cache;
use Blush\Tests\Fixtures\Container\Status;

/**
 * An exportable class: its plan holds only scalars, enums, and attribute
 * arguments that survive `var_export()`.
 */
#[Singleton]
#[Tag('plans', ['slug' => 'tagged'])]
final class Tagged
{
	public function __construct(
		public readonly Cache $cache,
		public readonly Status $status = Status::Active,
		public readonly int $limit = 10
	) {
	}
}
