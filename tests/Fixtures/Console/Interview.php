<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Console;

use Blush\Console\Attributes\Command;
use Blush\Console\ExitCode;
use Blush\Console\Output;
use Blush\Console\Prompt;

#[Command('interview', 'Asks questions.')]
final readonly class Interview
{
	public function __invoke(Output $output, Prompt $prompt): ExitCode
	{
		$name  = $prompt->ask('Name?', 'Anon');
		$color = $prompt->choice('Color?', ['red', 'blue'], 'red');
		$sure  = $prompt->confirm('Sure?', true);

		$output->line("{$name}|{$color}|" . ($sure ? 'yes' : 'no'));

		return ExitCode::Success;
	}
}
