<?php

/**
 * File names.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

use Closure;
use DateMalformedStringException;
use DateTimeImmutable;
use Blush\Content\Index\ContentIndex;
use Blush\Content\Index\IndexSnapshot;
use Blush\Content\Index\Indexer;
use Blush\Content\Parser\DocumentParser;
use Blush\Content\Parser\FrontMatter;
use Blush\Content\Parser\InvalidDocument;
use Blush\Content\Source\ContentSource;
use Blush\Content\Source\UnreadableSource;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Writer\ContentWriter;
use Blush\Content\Writer\RenamedFiles;
use Blush\Core\AppConfig;

/**
 * Finds the entries of each type named by another pattern than its
 * `filename` (D-511, any kind since D-514), and renames them to it
 * (D-512), for `content:filenames` and Content health in the admin
 * (D-478). A pattern only changes what comes before the slug, so a
 * rename never changes an entry's address.
 *
 * Only types that name a pattern themselves are renamed: one without
 * (Automatic) states no intent, and its prefixes may mean something a
 * pattern can't say, such as jtcom's same-day order
 * (`2008-04-05-3.bay-bay.md`).
 *
 * - **Files only:** a pattern never names folders (D-513), so an entry
 *   kept as a folder (`about/index.md`) keeps its name, and so does an
 *   entry with a translation kept as one (renaming it would break their
 *   link by name); those are `skipped`, with why.
 * - **What moves:** the entry's file and its translations linked by
 *   name (`hello.fr.md` beside `hello.md`), which keep their language
 *   suffixes, so they stay linked. A translation linked by
 *   `translation_of` keeps its name.
 * - **The date** is the entry's publish date as its file writes it, in
 *   the offset written there (`2013-02-09 00:00:00 -5` is the 9th,
 *   wherever the site is), or in the site's time zone without one;
 *   without a publish date, its `updated` date the same way, else the
 *   file's modified time.
 * - **Left alone:** landing pages, `_`-prefixed names, which a pattern
 *   would unhide, and entries whose date isn't a real date (`skipped`).
 *
 * It reads the index, brought up to date first.
 */
final readonly class FileNames
{
	public function __construct(
		private ContentIndex $index,
		private Indexer $indexer,
		private ContentTypes $types,
		private ContentWriter $writer,
		private AppConfig $app,
		private ContentSource $source,
		private DocumentParser $parser
	) {}

	/**
	 * Returns the entries to rename, and the ones left as they are with
	 * why, by type.
	 */
	public function report(): FileNameReport
	{
		$this->indexer->index();

		$snapshot = $this->index->snapshot();
		$renames  = [];
		$skipped  = [];

		foreach ($snapshot->records as $path => $record) {
			$path = (string) $path;
			$type = $this->types->find($record['type']);
			$name = pathinfo($path, PATHINFO_FILENAME);

			if ($type?->filename === null || $record['landing'] || $record['original'] !== null || str_starts_with($name, '_')) {
				continue;
			}

			if ($name === 'index') {
				$skipped[$type->name][$path] = 'it\'s kept as a folder, which a pattern never names';
				continue;
			}

			$date = $this->writtenDate($path, $type->name) ?? DateTimeImmutable::createFromTimestamp($record['updated'])->setTimezone($this->app->timezone());

			if (is_string($date)) {
				$skipped[$type->name][$path] = sprintf('its date, %s, isn\'t a real date; fix it first', $date);
				continue;
			}

			$newName = $type->naming()->name(substr($name, (int) strrpos(".{$name}", '.')), $date);

			if ($newName === $name) {
				continue;
			}

			$moves = self::moves($snapshot, $path, $name, $newName);

			if ($moves === null) {
				$skipped[$type->name][$path] = 'a translation of it is kept as a folder, so renaming it would break their link';
				continue;
			}

			$renames[$type->name][] = new FileNameRename($type->name, $path, $moves[$path], $moves);
		}

		return new FileNameReport($renames, $skipped);
	}

	/**
	 * Returns an entry's publish date, else its updated date, as its file
	 * writes it: in the offset written there, else the site's time zone,
	 * so a name gets the day the author wrote. A date that isn't on the
	 * calendar (a `2007-00-00` placeholder, which PHP rolls over) is
	 * returned as written, to leave the name alone. `null` when it has
	 * neither, or the file can't be read.
	 */
	private function writtenDate(string $path, string $type): DateTimeImmutable|string|null
	{
		try {
			$contents    = $this->source->read($path);
			$frontMatter = $this->parser->parse($contents)->frontMatter;
		} catch (InvalidDocument | UnreadableSource) {
			return null;
		}

		$schema = $this->types->schema($type);

		foreach (['published', 'updated'] as $name) {
			$field = $schema->field($name);

			foreach ([$name, ...$field->aliases ?? []] as $key) {
				// As written: YAML hands dates over already rolled.
				$value = FrontMatter::written($contents, $key) ?? $frontMatter[$key] ?? null;

				if (is_string($value) && preg_match('/^\s*(\d{4})-(\d{2})-(\d{2})/', $value, $day) === 1) {
					if (! checkdate((int) $day[2], (int) $day[3], (int) $day[1])) {
						return "{$day[1]}-{$day[2]}-{$day[3]}";
					}

					try {
						return new DateTimeImmutable(trim($value), $this->app->timezone());
					} catch (DateMalformedStringException) {
						continue;
					}
				}
			}
		}

		return null;
	}

	/**
	 * Renames the entries named by another pattern, of one type or every
	 * one, or only those `$allowed` passes (by path), such as the ones an
	 * account may edit.
	 *
	 * @param ?Closure(string): bool $allowed
	 */
	public function rename(?string $type = null, ?Closure $allowed = null): RenamedFiles
	{
		$moves = [];

		foreach ($this->report()->renames($type) as $rename) {
			if ($allowed === null || $allowed($rename->path)) {
				$moves[$rename->path] = $rename->moves;
			}
		}

		return $this->writer->renameFiles($moves);
	}

	/**
	 * Returns what moves to rename an entry's file: its own, and each
	 * translation linked by name, or `null` when a translation linked by
	 * name is kept as a folder.
	 *
	 * @return ?array<string, string>
	 */
	private static function moves(IndexSnapshot $snapshot, string $path, string $name, string $newName): ?array
	{
		$directory = dirname($path) === '.' ? '' : dirname($path) . '/';
		$moves     = [$path => $directory . $newName . substr(basename($path), strlen($name))];

		foreach ($snapshot->translations($path) as $translation) {
			$record = $snapshot->records[$translation] ?? null;

			if ($translation === $path || $record === null || $record['group'] !== null) {
				continue;
			}

			if (str_starts_with(basename($translation), 'index.')) {
				return null;
			}

			$moves[$translation] = $directory . $newName . substr(basename($translation), strlen($name));
		}

		return $moves;
	}
}
