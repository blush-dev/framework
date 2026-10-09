<?php

/**
 * Content lint command.
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
use Blush\Content\Lint\Linter;
use Blush\Content\Source\ContentFiles;
use Blush\Field\Severity;

/**
 * Checks every content file's front matter against its type's schema
 * (D-081, D-084), listing errors and warnings by file. `--strict` adds
 * notices: undeclared keys and 1.x aliases in use.
 * It also checks media metadata files (D-293): unreadable ones, values
 * that don't fit, and ones whose media file is gone. Any error fails the
 * command.
 */
#[Command('content:lint', 'Check content front matter against the schemas.')]
final readonly class LintContent
{
	public function __construct(
		private Linter $linter,
		private ContentFiles $files
	) {}

	public function __invoke(
		Output $output,
		#[Option('Also report undeclared keys and 1.x aliases.')] bool $strict = false
	): ExitCode {
		if (! $this->files->kept()) {
			$output->comment('The site keeps its content in a database, so there are no content files to check; data and media are checked.');
		}

		$bar    = $output->progress();
		$report = $this->linter->lint(static function (int $done, int $total) use ($bar): void {
			$bar->update($done, $total);
		});

		$bar->finish();

		foreach ($report->violations($strict ? Severity::Notice : Severity::Warning) as $path => $violations) {
			$output->line($output->style($path, Style::Bold));

			foreach ($violations as $violation) {
				$output->line(sprintf(
					'  %s %s: %s',
					$output->style(str_pad($violation->severity->value, 7), match ($violation->severity) {
						Severity::Error   => Style::Red,
						Severity::Warning => Style::Yellow,
						Severity::Notice  => Style::Dim
					}),
					$violation->field,
					$violation->message
				));
			}
		}

		$counts = [
			self::plural($report->count(Severity::Error), 'error'),
			self::plural($report->count(Severity::Warning), 'warning'),
			...($strict ? [self::plural($report->count(Severity::Notice), 'notice')] : [])
		];

		$checked = self::plural($report->checked, 'file') . ($report->metadata > 0 ? ' and ' . self::plural($report->metadata, 'media metadata file') : '');
		$summary = sprintf('Checked %s: %s.', $checked, implode(', ', $counts));

		if ($report->hasErrors()) {
			$output->error($summary);

			return ExitCode::Failure;
		}

		$output->success($summary);

		return ExitCode::Success;
	}

	/**
	 * Returns a count with its noun, pluralized.
	 */
	private static function plural(int $count, string $noun): string
	{
		return sprintf('%d %s%s', $count, $noun, $count === 1 ? '' : 's');
	}
}
