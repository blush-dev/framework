<?php

/**
 * Health check job.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Override;
use Blush\Content\Index\ContentIndex;
use Blush\Content\Index\Indexer;
use Blush\Content\Lint\Linter;
use Blush\Content\Lint\LintReport;
use Blush\Content\Source\ContentSource;
use Blush\Content\Source\SourceFile;
use Blush\Field\Severity;
use Blush\Field\Violation;
use Blush\Field\ViolationKind;
use Blush\Job\Job;
use Blush\Job\JobRecord;
use Blush\Job\JobResult;

/**
 * Checks content and media files again for Site Health (Check Again,
 * `blush/health-check`, D-625), a chunk at a time, so reading every
 * file never outlasts a request. Each run lints the next `CHUNK` content
 * files on their own (`Linter::lintFile()`), keeping what it found in
 * its data. The last brings the content index up to date, lints what
 * needs every file from it (`lintSite()`) and what isn't an entry
 * (`lintRest()`: media metadata, formats, field sets), and saves the
 * files' report and summary in Site Health's report.
 *
 * Its data: the content files `remaining` (`null` at first, for every
 * file), the `total`, how many were `checked`, and what was `found`, by
 * path.
 */
final class HealthCheckJob extends Job
{
	/**
	 * How many content files one run lints.
	 */
	public const int CHUNK = 200;

	public function __construct(
		private readonly ContentSource $source,
		private readonly Linter $linter,
		private readonly Indexer $indexer,
		private readonly ContentIndex $index,
		private readonly SiteHealth $health
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): string
	{
		return 'Check content and media files';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function handle(JobRecord $job): JobResult
	{
		$remaining = is_array($job->data['remaining'] ?? null)
			? array_values(array_filter($job->data['remaining'], is_string(...)))
			: array_map(static fn (SourceFile $file): string => $file->path, $this->source->files());

		$total   = is_int($job->data['total'] ?? null) ? $job->data['total'] : count($remaining);
		$checked = is_int($job->data['checked'] ?? null) ? $job->data['checked'] : 0;
		$found   = is_array($job->data['found'] ?? null) ? $job->data['found'] : [];

		foreach (array_slice($remaining, 0, self::CHUNK) as $path) {
			// A file removed since the check started isn't there to check.
			if ($this->source->stat($path) === null) {
				continue;
			}

			$checked++;
			$violations = $this->linter->lintFile($path);

			if ($violations !== []) {
				$found[$path] = array_map(self::toArray(...), $violations);
			}
		}

		$rest = array_slice($remaining, self::CHUNK);

		if ($rest !== []) {
			return JobResult::more(
				['remaining' => $rest, 'total' => $total, 'checked' => $checked, 'found' => $found],
				intdiv(($total - count($rest)) * 90, max(1, $total)),
				sprintf('Checked %d of %d content files.', $total - count($rest), $total)
			);
		}

		$this->indexer->index();

		$files = [];

		foreach ($found as $path => $violations) {
			$files[(string) $path] = array_values(array_filter(array_map(self::fromArray(...), is_array($violations) ? $violations : [])));
		}

		foreach ($this->linter->lintSite($this->index->snapshot()) as $path => $violations) {
			$files[$path] = [...$files[$path] ?? [], ...$violations];
		}

		[$others, $metadata] = $this->linter->lintRest();

		$this->health->checkFiles(lint: new LintReport($checked, [...$files, ...$others], $metadata));

		return JobResult::done(sprintf(
			'Checked %d content %s and %d media details %s.',
			$checked,
			$checked === 1 ? 'file' : 'files',
			$metadata,
			$metadata === 1 ? 'file' : 'files'
		));
	}

	/**
	 * Returns a violation as plain data, to keep between runs.
	 *
	 * @return array{field: string, message: string, severity: string, kind: ?string}
	 */
	private static function toArray(Violation $violation): array
	{
		return [
			'field'    => $violation->field,
			'message'  => $violation->message,
			'severity' => $violation->severity->value,
			'kind'     => $violation->kind?->value
		];
	}

	/**
	 * Returns a violation kept as plain data, or `null` for one that
	 * isn't.
	 */
	private static function fromArray(mixed $data): ?Violation
	{
		if (! is_array($data) || ! is_string($data['field'] ?? null) || ! is_string($data['message'] ?? null)) {
			return null;
		}

		$severity = is_string($data['severity'] ?? null) ? Severity::tryFrom($data['severity']) : null;
		$kind     = is_string($data['kind'] ?? null) ? ViolationKind::tryFrom($data['kind']) : null;

		return new Violation($data['field'], $data['message'], $severity ?? Severity::Error, $kind);
	}
}
