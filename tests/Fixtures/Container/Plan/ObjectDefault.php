<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container\Plan;

use Blush\Container\Attributes\NoAutowire;
use Blush\Tests\Fixtures\Container\FileCache;

/**
 * A class with an object default (`new` in an initializer). Its plan can't be
 * exported, and the default must be built fresh for every instance.
 */
final class ObjectDefault
{
	public function __construct(
		#[NoAutowire] public readonly FileCache $cache = new FileCache()
	) {
	}
}
