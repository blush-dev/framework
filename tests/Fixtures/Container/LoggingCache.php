<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container;

final class LoggingCache implements Cache
{
	public function __construct(public readonly Cache $inner)
	{}
}
