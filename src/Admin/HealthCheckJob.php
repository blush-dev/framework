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
use Blush\Content\Source\ContentFiles;
use Blush\Content\Source\SourceFile;
use Blush\Field\Severity;
use Blush\Field\Violation;
use Blush\Field\ViolationKind;
use Blush\Job\Job;
use Blush\Job\JobRecord;
use Blush\Job\JobResult;
use Blush\Media\MediaMetadataCheck;

/**
 * Checks content and media files again for Site Health (Check Again,
 * `blush/health-check`, D-625, D-626), a batch at a time, so reading
 * every file never outlasts a request. It goes in three stages, one or
 * more runs each, keeping what it found in its data:
 *
 * - `content`: the next `CHUNK` content files, each on its own
 *   (`Linter::lintFile()`);
 * - `media`: the next `CHUNK` media details files, each on its own
 *   (`MediaMetadataCheck::checkSome()`), noting those that can't be read;
 * - `finish`: brings the content index up to date, lints what needs every
 *   entry from it (`lintSite()`), checks what needs the whole library
 *   (`checkLibrary()`), formats and field sets, and saves the files'
 *   report and summary in Site Health's report.
 *
 * Its data: the `stage`, the files `remaining` in it (`null` at its
 * start, for every file), the `total` in it, how many content files were
 * `checked`, the media details files `described`, the `unreadable` ones,
 * and what was `found` in each stage, by path.
 */
final class HealthCheckJob extends Job
{
	/**
	 * How many files one run checks.
	 */
	public const int CHUNK = 200;

	public function __construct(
		private readonly ContentFiles $contentFiles,
		private readonly Linter $linter,
		private readonly MediaMetadataCheck $media,
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
		$data  = $job->data;
		$stage = is_string($data['stage'] ?? null) ? $data['stage'] : 'content';

		return match ($stage) {
			'media'  => $this->media($data),
			'finish' => $this->finish($data),
			default  => $this->content($data)
		};
	}

	/**
	 * Lints the next content files.
	 *
	 * @param array<string, mixed> $data
	 */
	private function content(array $data): JobResult
	{
		$remaining = self::strings($data['remaining'] ?? null) ?? ($this->contentFiles->kept() ? array_map(static fn (SourceFile $file): string => $file->path, $this->contentFiles->source()->files()) : []);
		$total     = is_int($data['total'] ?? null) ? $data['total'] : count($remaining);
		$checked   = is_int($data['checked'] ?? null) ? $data['checked'] : 0;
		$found     = is_array($data['found'] ?? null) ? $data['found'] : [];

		foreach (array_slice($remaining, 0, self::CHUNK) as $path) {
			// A file removed since the check started isn't there to check.
			if ($this->contentFiles->source()->stat($path) === null) {
				continue;
			}

			$checked++;
			$violations = $this->linter->lintFile($path);

			if ($violations !== []) {
				$found[$path] = array_map(self::toArray(...), $violations);
			}
		}

		$rest = array_slice($remaining, self::CHUNK);

		return JobResult::more(
			[...$data, 'stage' => $rest === [] ? 'media' : 'content', 'remaining' => $rest === [] ? null : $rest, 'total' => $rest === [] ? null : $total, 'checked' => $checked, 'found' => $found],
			intdiv(($total - count($rest)) * 60, max(1, $total)),
			sprintf('Checked %d of %d content files.', $total - count($rest), $total)
		);
	}

	/**
	 * Checks the next media details files.
	 *
	 * @param array<string, mixed> $data
	 */
	private function media(array $data): JobResult
	{
		$remaining  = self::strings($data['remaining'] ?? null) ?? $this->media->keys();
		$total      = is_int($data['total'] ?? null) ? $data['total'] : count($remaining);
		$described  = is_int($data['described'] ?? null) ? $data['described'] : 0;
		$unreadable = self::strings($data['unreadable'] ?? null) ?? [];
		$found      = is_array($data['mediaFound'] ?? null) ? $data['mediaFound'] : [];

		[$violations, $bad, $checked] = $this->media->checkSome(array_slice($remaining, 0, self::CHUNK));

		foreach ($violations as $path => $list) {
			$found[$path] = array_map(self::toArray(...), $list);
		}

		$rest = array_slice($remaining, self::CHUNK);

		return JobResult::more(
			[...$data, 'stage' => $rest === [] ? 'finish' : 'media', 'remaining' => $rest, 'total' => $total, 'described' => $described + $checked, 'unreadable' => [...$unreadable, ...$bad], 'mediaFound' => $found],
			60 + intdiv(($total - count($rest)) * 35, max(1, $total)),
			sprintf('Checked %d of %d media details files.', $total - count($rest), $total)
		);
	}

	/**
	 * Checks what needs every file, and saves the report.
	 *
	 * @param array<string, mixed> $data
	 */
	private function finish(array $data): JobResult
	{
		$checked   = is_int($data['checked'] ?? null) ? $data['checked'] : 0;
		$described = is_int($data['described'] ?? null) ? $data['described'] : 0;

		$files = self::violations($data['found'] ?? null);

		// What needs every file at once is checked over the filesystem
		// index; a database has no files (D-667).
		if ($this->contentFiles->kept()) {
			$this->indexer->index();

			foreach ($this->linter->lintSite($this->index->snapshot()) as $path => $violations) {
				$files[$path] = [...$files[$path] ?? [], ...$violations];
			}
		}

		$media = self::violations($data['mediaFound'] ?? null);

		foreach ($this->media->checkLibrary(self::strings($data['unreadable'] ?? null) ?? []) as $path => $violations) {
			$media[$path] = [...$media[$path] ?? [], ...$violations];
		}

		$this->health->checkFiles(lint: new LintReport($checked, [...$files, ...$this->linter->lintFormats(), ...$media, ...$this->linter->lintFieldSets()], $described));

		return JobResult::done(sprintf(
			'Checked %d content %s and %d media details %s.',
			$checked,
			$checked === 1 ? 'file' : 'files',
			$described,
			$described === 1 ? 'file' : 'files'
		));
	}

	/**
	 * Returns violations kept as plain data, by path.
	 *
	 * @return array<string, list<Violation>>
	 */
	private static function violations(mixed $kept): array
	{
		$violations = [];

		foreach (is_array($kept) ? $kept : [] as $path => $list) {
			$violations[(string) $path] = array_values(array_filter(array_map(self::fromArray(...), is_array($list) ? $list : [])));
		}

		return $violations;
	}

	/**
	 * Returns a list of strings from the job's data, or `null` when it
	 * isn't one.
	 *
	 * @return ?list<string>
	 */
	private static function strings(mixed $value): ?array
	{
		return is_array($value) ? array_values(array_filter($value, is_string(...))) : null;
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
