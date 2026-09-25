<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container;

final class NeedsStatus
{
	public function __construct(public readonly Status $status)
	{}
}
