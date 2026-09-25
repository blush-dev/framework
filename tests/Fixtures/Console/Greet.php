<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Console;

use Blush\Console\Attributes\Argument;
use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\Output;

#[Command('greet', 'Greet people.', aliases: ['hi'])]
final readonly class Greet
{
	/**
	 * @param list<string> $tag
	 */
	public function __invoke(
		Output $output,
		#[Argument('Who to greet.')] string $name,
		#[Argument('How many times.')] int $times = 1,
		#[Option('Shout it.', short: 's')] bool $loud = false,
		#[Option('The level.', short: 'l')] Level $level = Level::Low,
		#[Option('A ratio.')] ?float $ratio = null,
		#[Option('Tags.', short: 't')] array $tag = [],
		#[Option('Dry run.')] bool $dryRun = false
	): ExitCode {
		$greeting = "Hello, {$name}";
		$greeting = $loud ? strtoupper($greeting) : $greeting;

		for ($i = 0; $i < $times; $i++) {
			$output->line($greeting);
		}

		$output->line(sprintf(
			'level=%s ratio=%s tags=%s dry=%s',
			$level->value,
			var_export($ratio, true),
			implode(',', $tag),
			$dryRun ? 'yes' : 'no'
		));

		return ExitCode::Success;
	}
}
