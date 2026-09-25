<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Console;

use Blush\Console\Attributes\Command;

#[Command('bad:return')]
final readonly class BadReturn
{
	public function __invoke(): int
	{
		return 0;
	}
}
