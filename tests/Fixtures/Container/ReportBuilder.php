<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container;

final class ReportBuilder
{
	public function __construct(public readonly Cache $cache)
	{}
}
