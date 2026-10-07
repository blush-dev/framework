<?php

/**
 * Admin content health report.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Blush\Content\EntryIds;
use Blush\Content\FileNameRename;
use Blush\Content\FileNames;
use Blush\Content\FlatEntries;
use Blush\Content\Lint\Linter;
use Blush\Content\MissingTerms;
use Blush\Content\Type\ContentTypes;
use Blush\Core\Paths;
use Blush\Field\Severity;
use Blush\Field\Violation;
use Blush\Media\MediaException;
use Blush\Media\MediaIdReport;
use Blush\Media\MediaIds;
use Blush\Media\MediaSizeReport;
use Blush\Media\MediaSizes;

/**
 * What's wrong in content and media files, for Site Health's Content and
 * Media details (`GET health`) and its summary (`GET health/site`,
 * D-543): `content:lint`'s problems by file (D-225), media metadata
 * files included (D-293), each file with its `area` (`media` for a media
 * file or its metadata, else `content`); files missing an id and ids
 * files share, for entries (D-477) and media (D-487); images' sizes not
 * recorded (D-488); entries named by another pattern than their type's
 * (D-512); collections' entries kept in folders (D-514); and terms and
 * profiles entries name with no file (D-584).
 *
 * It reads every file, so it runs when asked.
 */
final readonly class ContentHealth
{
	public function __construct(
		private Linter $linter,
		private EntryIds $ids,
		private MediaIds $mediaIds,
		private MediaSizes $mediaSizes,
		private FileNames $fileNames,
		private ContentTypes $types,
		private FlatEntries $flat,
		private MissingTerms $terms,
		private Paths $paths
	) {}

	/**
	 * Returns the report: errors and warnings, and notices too when
	 * `strict`.
	 *
	 * @return array{checked: int, metadata: int, strict: bool, counts: array{error: int, warning: int, notice: ?int}, files: list<array{path: string, area: string, violations: list<array{field: string, message: string, severity: string}>}>, ids: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, mediaIds: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, fileNames: list<array{type: string, label: string, pattern: string, count: int, examples: list<array{path: string, to: string}>, skipped: int}>, flat: array{count: int, examples: list<array{path: string, to: string}>}, terms: array{count: int, examples: list<array{type: string, slug: string, title: string}>}, mediaSizes: array{sizes: int, images: int, stale: int}}
	 */
	public function report(bool $strict = false): array
	{
		$report = $this->linter->lint();
		$files  = [];

		foreach ($report->violations($strict ? Severity::Notice : Severity::Warning) as $path => $violations) {
			$files[] = [
				'path'       => (string) $path,
				'area'       => $this->isMedia((string) $path) ? 'media' : 'content',
				'violations' => array_map(static fn (Violation $violation): array => [
					'field'    => $violation->field,
					'message'  => $violation->message,
					'severity' => $violation->severity->value
				], $violations)
			];
		}

		$ids = $this->ids->report();

		try {
			$media = $this->mediaIds->report();
			$sizes = $this->mediaSizes->report();
		} catch (MediaException) {
			$media = new MediaIdReport();
			$sizes = new MediaSizeReport();
		}

		return [
			'checked'    => $report->checked,
			'metadata'   => $report->metadata,
			'strict'     => $strict,
			'counts'     => [
				'error'   => $report->count(Severity::Error),
				'warning' => $report->count(Severity::Warning),
				'notice'  => $strict ? $report->count(Severity::Notice) : null
			],
			'files'      => $files,
			'ids'        => self::ids($ids->missing, $ids->duplicates),
			'mediaIds'   => self::ids($media->missing, $media->duplicates),
			'fileNames'  => $this->fileNames(),
			'flat'       => $this->flatReport(),
			'terms'      => $this->termsReport(),
			'mediaSizes' => ['sizes' => $sizes->count(), 'images' => count($sizes->unrecorded), 'stale' => count($sizes->stale)]
		];
	}

	/**
	 * Whether a reported path is a media file's or its metadata's, both
	 * from the site root, where an entry's is from `user/content`.
	 */
	private function isMedia(string $path): bool
	{
		foreach ([$this->paths->media, "{$this->paths->data}/media"] as $folder) {
			if (str_starts_with($path, $this->paths->relative($folder) . '/')) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Answers how many collections' files aren't flat, and the first few
	 * moves.
	 *
	 * @return array{count: int, examples: list<array{path: string, to: string}>}
	 */
	private function flatReport(): array
	{
		$moves = $this->flat->report();

		return [
			'count'    => count($moves),
			'examples' => array_map(static fn (string $path, string $to): array => ['path' => $path, 'to' => $to], array_slice(array_keys($moves), 0, 3), array_slice(array_values($moves), 0, 3))
		];
	}

	/**
	 * Answers how many terms and profiles entries name have no file, and
	 * the first few.
	 *
	 * @return array{count: int, examples: list<array{type: string, slug: string, title: string}>}
	 */
	private function termsReport(): array
	{
		$missing = [];

		foreach ($this->terms->report() as $type => $slugs) {
			foreach ($slugs as $slug => $title) {
				$missing[] = ['type' => (string) $type, 'slug' => (string) $slug, 'title' => $title];
			}
		}

		return ['count' => count($missing), 'examples' => array_slice($missing, 0, 3)];
	}

	/**
	 * Answers the entries to rename to their type's pattern, by type.
	 *
	 * @return list<array{type: string, label: string, pattern: string, count: int, examples: list<array{path: string, to: string}>, skipped: int}>
	 */
	private function fileNames(): array
	{
		$report = $this->fileNames->report();
		$types  = [];

		foreach (array_keys($report->renames) as $name) {
			$type    = $this->types->find((string) $name);
			$renames = $report->renames((string) $name);

			if ($type === null || $renames === []) {
				continue;
			}

			$types[] = [
				'type'     => $type->name,
				'label'    => $type->labels->plural,
				'pattern'  => $type->naming()->pattern,
				'count'    => count($renames),
				'examples' => array_map(static fn (FileNameRename $rename): array => ['path' => $rename->path, 'to' => $rename->to], array_slice($renames, 0, 3)),
				'skipped'  => count($report->skipped[$type->name] ?? [])
			];
		}

		return $types;
	}

	/**
	 * Answers which files are missing an id and which ids files share.
	 *
	 * @param  list<string>                $missing
	 * @param  array<string, list<string>> $duplicates
	 * @return array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}
	 */
	private static function ids(array $missing, array $duplicates): array
	{
		return [
			'missing'    => $missing,
			'duplicates' => array_map(
				static fn (string $id, array $paths): array => ['id' => $id, 'paths' => $paths],
				array_map(strval(...), array_keys($duplicates)),
				array_values($duplicates)
			)
		];
	}
}
