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
use Blush\Console\Outcome;
use Blush\Console\Output;
use Blush\Console\StatusLine;
use Blush\Content\Lint\Linter;
use Blush\Content\Source\ContentFiles;
use Blush\Field\Severity;
use Blush\Field\Violation;

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

		$bar    = $output->progress(label: 'Checking content');
		$report = $this->linter->lint(static function (int $done, int $total) use ($bar): void {
			$bar->update($done, $total);
		});

		$bar->finish();

		foreach ($report->violations($strict ? Severity::Notice : Severity::Warning) as $path => $violations) {
			$output->section((string) $path);
			$output->status(array_map(
				static fn (Violation $violation): StatusLine => new StatusLine(Outcome::of($violation->severity), $violation->field, $violation->message),
				$violations
			));
		}

		$checked = Output::count($report->checked, 'file', 'files') . ($report->metadata > 0 ? ' and ' . Output::count($report->metadata, 'media metadata file', 'media metadata files') : '');

		$output->summary(
			$report->hasErrors() ? Outcome::Failure : ($report->count(Severity::Warning) > 0 ? Outcome::Warning : Outcome::Pass),
			"Checked {$checked}",
			...Output::problems($report->count(Severity::Error), $report->count(Severity::Warning), $strict ? $report->count(Severity::Notice) : null)
		);

		return $report->hasErrors() ? ExitCode::Failure : ExitCode::Success;
	}
}
