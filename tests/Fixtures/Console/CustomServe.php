<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Console;

use Blush\Console\Attributes\Command;
use Blush\Console\ExitCode;
use Blush\Console\Output;

#[Command('serve', 'A site-specific serve command.')]
final readonly class CustomServe
{
	public function __invoke(Output $output): ExitCode
	{
		$output->line('custom serve');

		return ExitCode::Success;
	}
}
