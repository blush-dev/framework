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
use Blush\Console\Output;
use Blush\Console\Style;
use Blush\Content\Type\TaxonomyMigration;

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
			$done = $this->migration->migrate();

			foreach ($done['migrated'] as $name => $files) {
				$output->line(sprintf('%s  %s (%s)', $output->style('migrated', Style::Green), $name, implode(', ', $files)));
			}

			foreach ($done['failed'] as $name => $message) {
				$output->line(sprintf('%s  %s: %s', $output->style('failed  ', Style::Red), $name, $message));
			}

			if ($done['failed'] !== []) {
				return ExitCode::Failure;
			}
		}

		$left = $this->migration->report();

		if ($left === []) {
			$output->success('No data type is written as a taxonomy.');

			return ExitCode::Success;
		}

		foreach ($left as $name) {
			$output->line(sprintf('%s  %s', $output->style('taxonomy', Style::Red), $name));
		}

		$output->error(sprintf('%d %s still written as %s; migrate %s with --write.', count($left), count($left) === 1 ? 'data type is' : 'data types are', count($left) === 1 ? 'a taxonomy' : 'taxonomies', count($left) === 1 ? 'it' : 'them'));

		return ExitCode::Failure;
	}
}
