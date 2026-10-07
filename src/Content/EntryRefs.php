<?php

/**
 * Entry refs.
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
use Blush\Content\Relation\LinkBuilder;
use Blush\Content\Relation\Relations;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Writer\ContentWriter;
use Blush\Content\Writer\FiledRefs;

/**
 * Finds and files the content files whose relations aren't filed in
 * both forms (D-589), for `content:refs` and Site Health (D-596): a file
 * written by hand, without `refs`; one naming an id where Blush writes a
 * slug; and one naming a target by a slug it no longer has, whose id
 * `refs` still follows. Blush files both whenever it writes a file, so
 * these are files written some other way.
 *
 * It reads the index, brought up to date first.
 */
final readonly class EntryRefs
{
	public function __construct(
		private ContentIndex $index,
		private Indexer $indexer,
		private Relations $relations,
		private ContentTypes $types,
		private ContentWriter $writer
	) {}

	/**
	 * Returns the files to file again, each with the names of the
	 * relations that differ, by path, in order.
	 *
	 * @return array<string, list<string>>
	 */
	public function report(): array
	{
		$this->indexer->index();

		$stale = [];

		foreach (new LinkBuilder()->build($this->index->snapshot(), $this->relations, $this->types)->stale as $path => $resolutions) {
			$stale[(string) $path] = array_map(strval(...), array_keys($resolutions));
		}

		ksort($stale, SORT_NATURAL);

		return $stale;
	}

	/**
	 * Files every file's relations, or only those `$allowed` passes (by
	 * path), such as the ones an account may edit.
	 *
	 * @param ?Closure(string): bool $allowed
	 */
	public function file(?Closure $allowed = null): FiledRefs
	{
		$paths = array_keys($this->report());

		return $this->writer->fileRefs($allowed === null ? $paths : array_values(array_filter($paths, $allowed)));
	}
}
