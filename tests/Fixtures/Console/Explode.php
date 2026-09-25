<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Console;

use RuntimeException;
use Blush\Console\Attributes\Command;
use Blush\Console\ExitCode;

#[Command('explode', 'Always fails.', hidden: true)]
final readonly class Explode
{
	public function __invoke(): ExitCode
	{
		throw new RuntimeException('Kaboom');
	}
}
