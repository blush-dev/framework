<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Console;

use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;

#[Command('bad:option')]
final readonly class OptionWithoutDefault
{
	public function __invoke(#[Option] string $name): ExitCode
	{
		return ExitCode::Success;
	}
}
