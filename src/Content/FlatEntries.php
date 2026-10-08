<?php

/**
 * Flat entries.
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
use Blush\Content\Type\Collection;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Writer\ContentWriter;
use Blush\Content\Writer\RenamedFiles;

/**
 * A collection is flat (D-514): its entries are files directly in its
 * folder. A folder entry (`_posts/hello/index.md`) or an entry in a
 * folder below (`_posts/2024/hello.md`) is a `content:lint` error, and
 * `content:flatten` and Site Health move each to its place
 * (`_posts/hello.md`), keeping its name (and language suffix), and
 * remove the folders left empty (D-478).
 *
 * Other types' folders inside a collection's hold other types' files,
 * and `_` folders (`_drafts`, a relation archive's `_authors`) may hold its
 * own, so both stay. It reads the index, brought up to date first.
 */
final readonly class FlatEntries
{
	public function __construct(
		private ContentIndex $index,
		private Indexer $indexer,
		private ContentTypes $types,
		private ContentWriter $writer
	) {}

	/**
	 * Returns where a type's file belongs, flat, or `null` when it's
	 * already there or the type isn't a collection: a folder entry's file
	 * is named by its folder (`hello/index.fr.md` is `hello.fr.md`), and
	 * folders below the type's are left out, except `_` folders.
	 */
	public static function flatPath(ContentType $type, string $path, bool $landing): ?string
	{
		if (! $type instanceof Collection || $landing) {
			return null;
		}

		$below    = $type->folder === '' ? $path : substr($path, strlen($type->folder) + 1);
		$segments = explode('/', $below);
		$file     = (string) array_pop($segments);
		$bundle   = $segments !== [] && str_starts_with($file, 'index.');

		if ($bundle) {
			$file = array_pop($segments) . substr($file, strlen('index'));
		}

		$kept = array_values(array_filter($segments, static fn (string $segment): bool => str_starts_with($segment, '_')));

		if (! $bundle && $kept === $segments) {
			return null;
		}

		return ltrim(implode('/', [$type->folder, ...$kept, $file]), '/');
	}

	/**
	 * Returns the files that aren't flat: where each belongs, by path.
	 *
	 * @return array<string, string>
	 */
	public function report(): array
	{
		$this->indexer->index();

		$moves = [];

		foreach ($this->index->snapshot()->records as $path => $record) {
			$type = $this->types->find($record['type']);
			$flat = $type === null ? null : self::flatPath($type, (string) $path, $record['landing']);

			if ($flat !== null) {
				$moves[(string) $path] = $flat;
			}
		}

		return $moves;
	}

	/**
	 * Moves every file that isn't flat to its place, or only those
	 * `$allowed` passes (by path).
	 *
	 * @param ?Closure(string): bool $allowed
	 */
	public function flatten(?Closure $allowed = null): RenamedFiles
	{
		$moves = [];

		foreach ($this->report() as $path => $flat) {
			if ($allowed === null || $allowed($path)) {
				$moves[$path] = [$path => $flat];
			}
		}

		return $this->writer->renameFiles($moves);
	}
}
