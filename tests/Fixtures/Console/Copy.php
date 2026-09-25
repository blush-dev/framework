<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Console;

use Blush\Console\Attributes\Argument;
use Blush\Console\Attributes\Command;
use Blush\Console\ExitCode;
use Blush\Console\Output;

#[Command('copy', 'Copy files.')]
final readonly class Copy
{
	public function __invoke(
		Output $output,
		#[Argument('The target.')] string $target,
		#[Argument('The sources.')] string ...$sources
	): ExitCode {
		$output->line($target . ' <- ' . implode(',', $sources));

		return ExitCode::Success;
	}
}
