<?php

/**
 * Migrate taxonomies command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\Outcome;
use Blush\Console\Output;
use Blush\Console\StatusLine;
use Blush\Content\Type\TaxonomyMigration;
use Blush\Core\Framework;

/**
 * Lists the data types in `user/data/types` still written as taxonomies
 * (D-591), and with `--write` rewrites each as a collection and its
 * classify relation (D-593, D-478). Without it, it fails when any is
 * left.
 */
#[Command('content:taxonomies', 'List data types still written as taxonomies, and migrate them to collections and relations.')]
final readonly class MigrateTaxonomies
{
	public function __construct(private TaxonomyMigration $migration)
	{}

	public function __invoke(
		Output $output,
		#[Option('Rewrite each as a collection and its classify relation.')] bool $write = false
	): ExitCode {
		if ($write) {
			$steps = $output->steps();

			try {
				$done = $this->migration->migrate($steps(...));
			} finally {
				$steps->finish();
			}

			$output->status([
				...array_map(static fn (string $name, array $files): StatusLine => new StatusLine(Outcome::Pass, 'migrated', sprintf('%s (%s)', $name, implode(', ', $files))), array_keys($done['migrated']), array_values($done['migrated'])),
				...array_map(static fn (string $name, string $message): StatusLine => new StatusLine(Outcome::Failure, 'failed', "{$name}: {$message}"), array_keys($done['failed']), array_values($done['failed']))
			]);

			if ($done['failed'] !== []) {
				return ExitCode::Failure;
			}
		}

		$left = $this->migration->report();

		if ($left === []) {
			$output->success('No data type is written as a taxonomy.');

			return ExitCode::Success;
		}

		$output->status(array_map(static fn (string $name): StatusLine => new StatusLine(Outcome::Failure, 'taxonomy', $name), $left));
		$output->error(
			sprintf('%s still written as %s.', Output::count(count($left), 'data type is', 'data types are'), count($left) === 1 ? 'a taxonomy' : 'taxonomies'),
			'Migrate ' . (count($left) === 1 ? 'it' : 'them') . ' with `' . Framework::BINARY . ' content:taxonomies --write`.'
		);

		return ExitCode::Failure;
	}
}
