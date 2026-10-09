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

use Blush\Content\Index\EntryFiles;
use Blush\Content\EntryIdReport;
use Blush\Content\EntryIds;
use Blush\Content\EntryRefs;
use Blush\Content\FileNameRename;
use Blush\Content\FileNames;
use Blush\Content\EntryFolders;
use Blush\Content\Lint\Linter;
use Blush\Content\Lint\LintReport;
use Blush\Content\MissingTerms;
use Blush\Content\Source\ContentSource;
use Blush\Content\Source\FilesystemSource;
use Blush\Content\Type\ContentTypes;
use Blush\Core\Paths;
use Blush\Field\Severity;
use Blush\Field\Violation;
use Blush\Field\ViolationKind;
use Blush\Media\MediaException;
use Blush\Media\MediaIdReport;
use Blush\Media\MediaIds;
use Blush\Media\MediaMetadataStore;
use Blush\Media\MediaSizeReport;
use Blush\Media\MediaSizes;

/**
 * What's wrong in content and media files, for Site Health's Content and
 * Media details (`GET health`) and its summary (`GET health/site`,
 * D-543): `content:lint`'s problems by file (D-225), media metadata
 * files included (D-293), each file with its `area` (`media` for a media
 * file or its metadata, else `content`) and each problem with its `kind`
 * (D-612); files missing an id and ids files share, for entries (D-477)
 * and media (D-487); images' sizes not recorded (D-488); entries named
 * by another pattern than their type's (D-512); collections' entries
 * kept in folders (D-514); terms and profiles entries name with no file
 * (D-584); and links between entries not filed with their ids (D-596).
 *
 * Each check's page lists every problem it found, one row each (D-612),
 * so the report lists them all, not the first few, with `entries`, the
 * title, type, and id of each content file it names, by path. A problem
 * another check reports (an id, a term with no file, an entry in a
 * folder, an image's sizes) is left out of the files' problems: one
 * problem, one check. Its `version` changes with its shape, so a kept
 * report in an older one is checked again.
 *
 * It reads every file, so it runs when asked.
 */
final readonly class ContentHealth
{
	/**
	 * The report's shape: a kept report in another is checked again.
	 */
	public const int VERSION = 2;

	/**
	 * The kinds of problem another check reports.
	 *
	 * @var list<ViolationKind>
	 */
	private const array ELSEWHERE = [ViolationKind::Id, ViolationKind::Term, ViolationKind::Folder, ViolationKind::Sizes];

	public function __construct(
		private Linter $linter,
		private EntryIds $ids,
		private MediaIds $mediaIds,
		private MediaSizes $mediaSizes,
		private FileNames $fileNames,
		private ContentTypes $types,
		private EntryFolders $folders,
		private MissingTerms $terms,
		private EntryRefs $refs,
		private EntryFiles $files,
		private MediaMetadataStore $metadata,
		private Paths $paths,
		private ContentSource $source
	) {}

	/**
	 * Returns the report: errors and warnings, and notices too when
	 * `strict`. `$lint` is a lint already done, a chunk at a time
	 * (`HealthCheckJob`, D-625); without it, every file is linted now.
	 *
	 * @return array{version: int, checked: int, metadata: int, strict: bool, counts: array{error: int, warning: int, notice: ?int}, files: list<array{path: string, area: string, violations: list<array{field: string, message: string, severity: string, kind: ?string}>}>, entries: array<string, array{title: string, type: string, id: ?string}>, ids: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, mediaIds: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, fileNames: list<array{type: string, label: string, pattern: string, count: int, items: list<array{path: string, to: string}>, skipped: int}>, folders: array{count: int, items: list<array{path: string, to: string}>}, terms: array{count: int, items: list<array{type: string, label: string, slug: string, title: string, entries: int}>}, refs: array{count: int, items: list<array{path: string, relations: list<string>}>}, taxonomies: list<string>, mediaSizes: array{sizes: int, images: int, stale: int, items: list<array{key: string, unrecorded: int, stale: int}>}}
	 */
	public function report(bool $strict = false, ?LintReport $lint = null): array
	{
		$report   = $lint ?? $this->linter->lint();
		$files    = [];
		$counts   = ['error' => 0, 'warning' => 0, 'notice' => 0];
		$metadata = array_flip(array_column($this->metadata->files(), 'location'));

		foreach ($report->violations($strict ? Severity::Notice : Severity::Warning) as $path => $violations) {
			$kept = array_values(array_filter($violations, static fn (Violation $violation): bool => ! in_array($violation->kind, self::ELSEWHERE, true)));

			if ($kept === []) {
				continue;
			}

			foreach ($kept as $violation) {
				$counts[$violation->severity->value]++;
			}

			$files[] = [
				'path'       => (string) $path,
				'area'       => $this->isMedia((string) $path, $metadata) ? 'media' : 'content',
				'violations' => array_map(static fn (Violation $violation): array => [
					'field'    => $violation->field,
					'message'  => $violation->message,
					'severity' => $violation->severity->value,
					'kind'     => $violation->kind?->value
				], $kept)
			];
		}

		// What only files have (ids missing from files, names, folders,
		// terms named without a file, both forms of relations) is checked
		// only on content kept as files (D-654).
		$onFiles = $this->source instanceof FilesystemSource;
		$ids     = $onFiles ? $this->ids->report() : new EntryIdReport();

		try {
			$media = $this->mediaIds->report();
			$sizes = $this->mediaSizes->report();
		} catch (MediaException) {
			$media = new MediaIdReport();
			$sizes = new MediaSizeReport();
		}

		$result = [
			'version'    => self::VERSION,
			'checked'    => $report->checked,
			'metadata'   => $report->metadata,
			'strict'     => $strict,
			'counts'     => [
				'error'   => $counts['error'],
				'warning' => $counts['warning'],
				'notice'  => $strict ? $counts['notice'] : null
			],
			'files'      => $files,
			'entries'    => [],
			'ids'        => self::ids($ids->missing, $ids->duplicates),
			'mediaIds'   => self::ids($media->missing, $media->duplicates),
			'fileNames'  => $onFiles ? $this->fileNames() : [],
			'folders'    => $onFiles ? $this->foldersReport() : ['count' => 0, 'items' => []],
			'terms'      => $onFiles ? $this->termsReport() : ['count' => 0, 'items' => []],
			'refs'       => $onFiles ? $this->refsReport() : ['count' => 0, 'items' => []],
			'taxonomies' => $this->types->legacy,
			'mediaSizes' => [
				'sizes'  => $sizes->count(),
				'images' => count($sizes->unrecorded),
				'stale'  => count($sizes->stale),
				'items'  => array_map(static fn (string $key): array => [
					'key'        => $key,
					'unrecorded' => count($sizes->unrecorded[$key] ?? []),
					'stale'      => count($sizes->stale[$key] ?? [])
				], $sizes->images())
			]
		];

		$result['entries'] = $this->entries([
			...array_column(array_filter($files, static fn (array $file): bool => $file['area'] === 'content'), 'path'),
			...$result['ids']['missing'],
			...array_merge(...array_column($result['ids']['duplicates'], 'paths')),
			...array_column($result['folders']['items'], 'path'),
			...array_column($result['refs']['items'], 'path'),
			...array_merge(...array_map(static fn (array $names): array => array_column($names['items'], 'path'), $result['fileNames']))
		]);

		return $result;
	}

	/**
	 * Returns the title, type, and id of each content file named, by
	 * path, for those the site can read.
	 *
	 * @param  list<string> $paths
	 * @return array<string, array{title: string, type: string, id: ?string}>
	 */
	private function entries(array $paths): array
	{
		$entries = [];

		foreach (array_unique($paths) as $path) {
			$entry = $this->files->at($path);

			if ($entry !== null) {
				$entries[$path] = ['title' => $entry->title, 'type' => $entry->type->name, 'id' => $entry->id];
			}
		}

		return $entries;
	}

	/**
	 * Whether a reported path is a media file's, from the site root, or
	 * where its metadata is kept (D-642), where an entry's is from
	 * `user/content`.
	 *
	 * @param array<string, int> $metadata Where metadata is kept.
	 */
	private function isMedia(string $path, array $metadata): bool
	{
		return isset($metadata[$path]) || str_starts_with($path, $this->paths->relative($this->paths->media) . '/');
	}

	/**
	 * Answers how many collections' and profiles' files aren't in their
	 * folders (D-514, D-629), and each move.
	 *
	 * @return array{count: int, items: list<array{path: string, to: string}>}
	 */
	private function foldersReport(): array
	{
		$moves = $this->folders->report();

		return [
			'count' => count($moves),
			'items' => array_map(static fn (string $path, string $to): array => ['path' => $path, 'to' => $to], array_map(strval(...), array_keys($moves)), array_values($moves))
		];
	}

	/**
	 * Answers how many terms and profiles entries name have no file, and
	 * each, with its type's label, the title its file gets, and how many
	 * entries name it.
	 *
	 * @return array{count: int, items: list<array{type: string, label: string, slug: string, title: string, entries: int}>}
	 */
	private function termsReport(): array
	{
		$missing = [];
		$report  = $this->terms->report();
		$named   = $this->terms->namedBy();

		foreach ($report as $type => $slugs) {
			$label = $this->types->find((string) $type)?->labels->singular ?? (string) $type;

			foreach ($slugs as $slug => $title) {
				$missing[] = ['type' => (string) $type, 'label' => $label, 'slug' => (string) $slug, 'title' => $title, 'entries' => $named[$type][$slug] ?? 0];
			}
		}

		return ['count' => count($missing), 'items' => $missing];
	}

	/**
	 * Answers how many files have links between entries not filed with
	 * their ids (D-596), and each, with the relations that differ.
	 *
	 * @return array{count: int, items: list<array{path: string, relations: list<string>}>}
	 */
	private function refsReport(): array
	{
		$stale = $this->refs->report();

		return [
			'count' => count($stale),
			'items' => array_map(static fn (string $path, array $relations): array => ['path' => $path, 'relations' => $relations], array_map(strval(...), array_keys($stale)), array_values($stale))
		];
	}

	/**
	 * Answers the entries to rename to their type's pattern, by type.
	 *
	 * @return list<array{type: string, label: string, pattern: string, count: int, items: list<array{path: string, to: string}>, skipped: int}>
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
				'type'    => $type->name,
				'label'   => $type->labels->plural,
				'pattern' => $type->naming()->pattern,
				'count'   => count($renames),
				'items'   => array_map(static fn (FileNameRename $rename): array => ['path' => $rename->path, 'to' => $rename->to], $renames),
				'skipped' => count($report->skipped[$type->name] ?? [])
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
