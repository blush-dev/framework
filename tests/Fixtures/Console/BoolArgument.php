<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Console;

use Blush\Console\Attributes\Argument;
use Blush\Console\Attributes\Command;
use Blush\Console\ExitCode;

#[Command('bad:bool')]
final readonly class BoolArgument
{
	public function __invoke(#[Argument] bool $flag = false): ExitCode
	{
		return ExitCode::Success;
	}
}
