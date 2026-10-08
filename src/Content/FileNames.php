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
use Blush\Content\Index\ContentIndex;
use Blush\Content\Index\IndexSnapshot;
use Blush\Content\Index\Indexer;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Writer\ContentWriter;
use Blush\Content\Writer\RenamedFiles;

/**
 * Finds the entries of each type named by another pattern than its
 * `filename` (D-511, any kind since D-514), and renames them to it
 * (D-512), for `content:filenames` and Site Health in the admin
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
 * - **The date** is the entry's publish date as its file writes it
 *   (`WrittenDates`); without a publish date, its `updated` date the
 *   same way, else the file's modified time.
 * - **Folders** are `EntryFolders`' (D-629): a name's rename stays in
 *   the file's folder, and `follow()` moves it to its folder too.
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
		private WrittenDates $dates,
		private EntryFolders $folders
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
			$plan = $this->plan($snapshot, $path);

			if ($type === null || $plan === null) {
				continue;
			}

			if (is_string($plan)) {
				$skipped[$type->name][$path] = $plan;
			} else {
				$renames[$type->name][] = $plan;
			}
		}

		return new FileNameReport($renames, $skipped);
	}

	/**
	 * Renames one entry to its type's pattern after its publish date
	 * changed (D-519), when the pattern has a date in it, so the name
	 * keeps showing the date, then moves it to the folder its type's
	 * folder pattern gives its date and slug (`EntryFolders::follow()`,
	 * D-629), which a new slug calls for too.
	 * Returns the entry's new path, or `null` when it stays where it is:
	 * its type's patterns have no date, the name and folder already fit,
	 * it's left alone (as `report()` leaves it), or the new path is
	 * taken.
	 */
	public function follow(string $path): ?string
	{
		$renamed = $this->followName($path);

		return $this->folders->follow($renamed ?? $path) ?? $renamed;
	}

	/**
	 * Renames one entry to its type's dated pattern, for `follow()`.
	 */
	private function followName(string $path): ?string
	{
		$snapshot = $this->index->snapshot();
		$record   = $snapshot->records[$path] ?? null;
		$type     = $record === null ? null : $this->types->find($record['type']);

		if ($type?->filename === null || ! $type->filename->isDated()) {
			return null;
		}

		$plan = $this->plan($snapshot, $path);

		if (! $plan instanceof FileNameRename) {
			return null;
		}

		return $this->writer->renameFiles([$path => $plan->moves])->renamed[$path] ?? null;
	}

	/**
	 * Returns how an entry is renamed to its type's pattern, why it's
	 * left as it is, or `null` when there's nothing to do: its type names
	 * no pattern, it's a landing page, a translation, or hidden, or its
	 * name already fits.
	 */
	private function plan(IndexSnapshot $snapshot, string $path): FileNameRename|string|null
	{
		$record = $snapshot->records[$path] ?? null;
		$type   = $record === null ? null : $this->types->find($record['type']);
		$name   = pathinfo($path, PATHINFO_FILENAME);

		if ($record === null || $type?->filename === null || $record['landing'] || $record['original'] !== null || str_starts_with($name, '_')) {
			return null;
		}

		if ($name === 'index') {
			return 'it\'s kept as a folder, which a pattern never names';
		}

		$date = $this->dates->orUpdated($path, $type->name, $record['updated']);

		if (is_string($date)) {
			return sprintf('its date, %s, isn\'t a real date; fix it first', $date);
		}

		$newName = $type->naming()->name(substr($name, (int) strrpos(".{$name}", '.')), $date);

		if ($newName === $name) {
			return null;
		}

		$moves = self::moves($snapshot, $path, $name, $newName);

		return $moves === null
			? 'a translation of it is kept as a folder, so renaming it would break their link'
			: new FileNameRename($type->name, $path, $moves[$path], $moves);
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
