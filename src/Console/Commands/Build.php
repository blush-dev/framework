<?php

/**
 * Build command.
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
use Blush\Console\InvalidInput;
use Blush\Console\Output;
use Blush\Console\Verbosity;
use Blush\Core\Paths;
use Blush\Export\ExportConfig;
use Blush\Export\Exporter;
use Blush\Export\ExportException;

/**
 * Exports the site to static files in `storage/export` (D-011): every
 * page rendered as production serves it, plus theme assets, media,
 * `public/`'s files, redirects, and host files. `--base-url` exports for
 * another origin, `--no-crawl` renders only the URLs the sources list,
 * and `--incremental` keeps the last export's pages when nothing they
 * depend on changed (D-139). Links to missing pages and what host files
 * can't express are warnings; a URL that errors fails the build, so
 * deploy scripts notice. `-v` lists what was left out.
 */
#[Command('build', 'Export the site to static files.')]
final readonly class Build
{
	public function __construct(
		private Exporter $exporter,
		private Paths $paths
	) {}

	/**
	 * @throws InvalidInput
	 */
	public function __invoke(
		Output $output,
		#[Option('The origin the export is served from, such as https://example.com.')] ?string $baseUrl = null,
		#[Option('Don\'t follow links to find more URLs.')] bool $noCrawl = false,
		#[Option('Keep the last export\'s pages when nothing changed.')] bool $incremental = false
	): ExitCode {
		if ($baseUrl !== null && ! ExportConfig::isOrigin($baseUrl)) {
			throw new InvalidInput(sprintf('The "--base-url" option must be an http(s) origin, such as "https://example.com"; "%s" given.', $baseUrl));
		}

		$bar = $output->progress();

		try {
			$report = $this->exporter->export($baseUrl, $noCrawl ? false : null, $incremental, static function (int $done, int $total) use ($bar): void {
				$bar->update($done, $total);
			});
		} catch (ExportException $error) {
			$bar->finish();
			$output->error($error->getMessage());

			return ExitCode::Failure;
		}

		$bar->finish();

		foreach ($report->index->failures ?? [] as $path => $message) {
			$output->error(sprintf('%s: %s', $path, $message));
		}

		foreach ($report->failures as $path => $message) {
			$output->error(sprintf('%s: %s', $path, $message));
		}

		foreach ($report->broken as $path => $referrer) {
			$output->warning(sprintf('%s (linked from %s) is not a page.', $path, $referrer));
		}

		foreach ($report->notices as $notice) {
			$output->warning($notice);
		}

		if (! $report->rendered) {
			$output->comment('Nothing changed since the last export; its pages were kept.');
		}

		foreach ($report->redirects as $path => $location) {
			$output->line(sprintf('Redirect: %s → %s', $path, $location), Verbosity::Verbose);
		}

		foreach ($report->skipped as $path) {
			$output->line(sprintf('Skipped: %s (not a page)', $path), Verbosity::Verbose);
		}

		foreach ($report->removed as $file) {
			$output->line(sprintf('Removed: %s', $file), Verbosity::Verbose);
		}

		$summary = sprintf(
			'Exported %d %s and %d %s to %s for %s in %d ms (%d written, %d unchanged, %d removed).',
			$report->pages,
			$report->pages === 1 ? 'page' : 'pages',
			$report->files,
			$report->files === 1 ? 'file' : 'files',
			$this->paths->relative($report->path),
			$report->url,
			$report->milliseconds,
			$report->written,
			$report->unchanged,
			count($report->removed)
		);

		if (! $report->isSuccessful()) {
			$output->error(sprintf('%s %d URL(s) or file(s) failed.', $summary, count($report->failures) + count($report->index->failures ?? [])));

			return ExitCode::Failure;
		}

		$output->success($summary);

		return ExitCode::Success;
	}
}
