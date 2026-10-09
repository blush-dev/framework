<?php

/**
 * Media usage.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use Closure;
use Blush\Content\Entries;
use Blush\Content\Index\EntryFiles;
use Blush\Content\Record\EntryRecords;
use Blush\Content\Record\EntryTable;
use Blush\Content\Source\ContentFiles;
use Blush\Content\Source\UnreadableSource;
use Blush\Storage\Record\Operator;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordStores;
use Blush\Support\UrlPath;

/**
 * Finds the entries that use a media file (D-407), so deleting it can say
 * what would break: every content document whose text names the file by
 * any address the resolver takes for it (the media URL, `/user/media`,
 * or `user/media`, written plainly or encoded), in its body or its front
 * matter. It reads the documents, so it's for one file at a time.
 */
final readonly class MediaUsage
{
	public function __construct(
		private ContentFiles $contentFiles,
		private EntryFiles $files,
		private MediaConfig $config,
		private RecordStores $stores,
		private Entries $content
	) {}

	/**
	 * The entries that use a file, by its path in `user/media`
	 * (`2026/10/photo.jpg`), or any of `$also` (an image's sizes, D-488), each with its `id` (`null` without one), `path` (its document's path), `type` (the type's name), `typeLabel`,
	 * `title` (the document's path when it isn't indexed), and `type`
	 * (the type's singular label, or `''`).
	 *
	 * @return list<array{id: ?string, path: string, title: string, type: string, typeLabel: string}>
	 */
	public function entries(string $relative, string ...$also): array
	{
		$paths = array_values(array_filter(array_map(static fn (string $path): string => trim($path, '/'), [$relative, ...$also]), static fn (string $path): bool => $path !== ''));

		if ($paths === []) {
			return [];
		}

		$names   = array_unique([...$paths, ...array_map(UrlPath::encode(...), $paths)]);
		$prefix  = '(?:' . preg_quote($this->config->url, '#') . '|/?user/media)/';
		$pattern = '#' . $prefix . '(?:' . implode('|', array_map(static fn (string $name): string => preg_quote($name, '#'), $names)) . ')(?![A-Za-z0-9._%~-])#';
		$found   = [];

		if (! $this->contentFiles->kept()) {
			return $this->inRecords(array_values($names), $pattern);
		}

		try {
			$files = $this->contentFiles->source()->files();
		} catch (UnreadableSource) {
			return [];
		}

		foreach ($files as $file) {
			try {
				$text = $this->contentFiles->source()->read($file->path);
			} catch (UnreadableSource) {
				continue;
			}

			if (preg_match($pattern, $text) !== 1) {
				continue;
			}

			$entry   = $this->files->at($file->path);
			$found[] = [
				'id'    => $entry?->id,
				'path'  => $file->path,
				'title'     => $entry === null || $entry->title === '' ? $file->path : $entry->title,
				'type'      => $entry?->type->name ?? '',
				'typeLabel' => $entry?->type->labels->singular ?? ''
			];
		}

		return $found;
	}

	/**
	 * The entries kept in a database whose Markdown uses a file (D-667):
	 * those whose content names it, found by the store, then checked as a
	 * file's text is.
	 *
	 * @param  list<string> $names
	 * @return list<array{id: ?string, path: string, title: string, type: string, typeLabel: string}>
	 */
	private function inRecords(array $names, string $pattern): array
	{
		$query = $this->stores->query(EntryTable::table());
		$query = $query->whereAny(...array_map(
			static fn (string $name): Closure => static fn (RecordQuery $query): RecordQuery => $query->where('content', Operator::Like, '%' . str_replace(['%', '_'], ['\\%', '\\_'], $name) . '%'),
			$names
		));
		$found = [];

		foreach ($query->get() as $record) {
			if (preg_match($pattern, $record->content ?? '') !== 1) {
				continue;
			}

			$entry   = $this->content->find($record->id);
			$found[] = [
				'id'        => $record->id,
				'path'      => $entry === null ? '' : $entry->key,
				'title'     => $entry === null || $entry->title === '' ? EntryRecords::text($record, 'slug') : $entry->title,
				'type'      => $entry?->type->name ?? '',
				'typeLabel' => $entry?->type->labels->singular ?? ''
			];
		}

		return $found;
	}
}
