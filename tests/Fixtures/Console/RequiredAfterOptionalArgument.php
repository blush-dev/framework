<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Console;

use Blush\Console\Attributes\Argument;
use Blush\Console\Attributes\Command;
use Blush\Console\ExitCode;

#[Command('bad:required')]
final readonly class RequiredAfterOptionalArgument
{
	public function __invoke(
		#[Argument] ?string $first,
		#[Argument] string $second
	): ExitCode {
		return ExitCode::Success;
	}
}
