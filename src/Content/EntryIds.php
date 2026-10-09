<?php

/**
 * Entry ids.
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
use Blush\Content\Index\Indexer;
use Blush\Content\Writer\AssignedIds;
use Blush\Content\Writer\FilesystemWriter;
use Blush\Content\Writer\WriteException;

/**
 * Finds and fixes the content files whose ids (D-477) are missing or
 * shared, for `content:ids` and Site Health in the admin (D-478):
 *
 * - **Missing:** a file with no `id`, or one that isn't a UUID, is given
 *   a new one.
 * - **Shared:** files with one id (a copied file) aren't guessed at.
 *   Whoever fixes them says which file keeps it, and the others are
 *   given new ones.
 *
 * It reads the index, brought up to date first, so files that can't be
 * parsed aren't listed (`content:lint` reports those).
 */
final readonly class EntryIds
{
	public function __construct(
		private ContentIndex $index,
		private Indexer $indexer,
		private FilesystemWriter $writer
	) {}

	/**
	 * Returns the files missing a valid id, and the ids files share.
	 */
	public function report(): EntryIdReport
	{
		$this->indexer->index();

		$snapshot = $this->index->snapshot();
		$missing  = [];

		foreach ($snapshot->records as $path => $record) {
			if ($record['id'] === null) {
				$missing[] = (string) $path;
			}
		}

		return new EntryIdReport($missing, $snapshot->duplicates);
	}

	/**
	 * Gives every file missing a valid id a new one, or only those
	 * `$allowed` passes (by path), such as the ones an account may edit.
	 *
	 * @param ?Closure(string): bool $allowed
	 */
	public function assignMissing(?Closure $allowed = null): AssignedIds
	{
		$missing = $this->report()->missing;

		return $this->writer->assignIds($allowed === null ? $missing : array_values(array_filter($missing, $allowed)));
	}

	/**
	 * Keeps a file's id on it and gives the other files sharing the id
	 * new ones, or only those `$allowed` passes (by path).
	 *
	 * @param  ?Closure(string): bool $allowed
	 * @throws WriteException When the file doesn't share its id.
	 */
	public function keep(string $path, ?Closure $allowed = null): AssignedIds
	{
		$report = $this->report();
		$shared = array_find($report->duplicates, static fn (array $paths): bool => in_array($path, $paths, true));

		if ($shared === null) {
			throw new WriteException(sprintf('%s doesn\'t share its id with another file.', $path));
		}

		$others = array_values(array_diff($shared, [$path]));

		return $this->writer->assignIds($allowed === null ? $others : array_values(array_filter($others, $allowed)));
	}
}
