<?php

/**
 * Entry files.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Index;

use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Entry\EntryHydrator;
use Blush\Content\Parser\DocumentParser;
use Blush\Content\Source\ContentSource;
use Blush\Storage\Record\Record;
use Blush\Support\Uuid;

/**
 * The entry kept in a content file, by its path: for the filesystem
 * driver's own tools (D-654), such as Site Health's checks of files,
 * which find problems by file. A file with an id is its entry; one
 * without isn't an entry (D-656), so it's built here alone, without an
 * id, for the fixes that give it one. Code that isn't about files finds
 * entries through `Entries`, by id or key.
 */
final readonly class EntryFiles
{
	public function __construct(
		private IndexFreshness $freshness,
		private EntryHydrator $hydrator,
		private Entries $content,
		private ContentSource $source,
		private DocumentParser $parser
	) {}

	/**
	 * Returns the entry kept at a path, whatever its status, or `null`.
	 */
	public function at(string $path): ?Entry
	{
		$index  = $this->freshness->fresh();
		$record = $index->snapshot()->records[$path] ?? null;

		if ($record === null) {
			return null;
		}

		if ($record['id'] !== null && $index->records()->path($record['id']) === $path) {
			return $this->content->find($record['id']);
		}

		return $this->hydrator->hydrate(
			new Record(Uuid::fromName("content/{$path}"), SnapshotRecords::fields($record, null, null), version: $record['hash']),
			$record['key'],
			$path,
			content: fn (): string => $this->parser->parse($this->source->read($path))->body,
			identified: false
		);
	}
}
