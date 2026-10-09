<?php

/**
 * Entry folders.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

use Closure;
use DateTimeImmutable;
use Blush\Content\Index\ContentIndex;
use Blush\Content\Index\IndexSnapshot;
use Blush\Content\Index\Indexer;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Writer\FilesystemWriter;
use Blush\Content\Writer\RenamedFiles;

/**
 * Keeps a collection's and the profiles' files where their type keeps
 * them (D-514, D-629): directly in its folder, or in the folders its
 * folder pattern gives (`_posts/{year}` keeps a post published in 2026
 * in `_posts/2026`). A folder entry (`_posts/hello/index.md`), or a file
 * in other folders, is a `content:lint` error when its folders don't
 * have the pattern's shape, and `content:folders` and Site Health move
 * each file to its folder, keeping its name (a folder entry's is its
 * folder's, `hello/index.fr.md` is `hello.fr.md`), and remove the
 * folders left empty (D-478).
 *
 * - **The date** a pattern's folders are named by is the entry's
 *   written date (`WrittenDates`), else its `updated` date; one that
 *   isn't on the calendar leaves the file where it is.
 * - **Translations linked by name** (`hello.fr.md` beside `hello.md`)
 *   move with their original, so they stay linked; one linked by
 *   `translation_of` is placed by its own date.
 * - **Left alone:** landing pages, trees (whose folders are their
 *   pages'), and other types' folders inside a type's (they hold other
 *   types' files). A hidden file (`_`-named, or in a `_` folder such as
 *   `_drafts` or a relation archive's `_authors`) stays in its folder,
 *   whatever the pattern, as a file name pattern leaves it (D-512); one
 *   kept as a folder still becomes a file there.
 *
 * Folders are only where files are kept, so a move never changes a key
 * or an address. It reads the index, brought up to date first.
 */
final readonly class EntryFolders
{
	public function __construct(
		private ContentIndex $index,
		private Indexer $indexer,
		private ContentTypes $types,
		private FilesystemWriter $writer,
		private WrittenDates $dates
	) {}

	/**
	 * Returns why a type's file isn't in a folder of the shape its type
	 * keeps files in, finishing a sentence, or `null` when it is: a folder
	 * entry, or folders below the type's (other than `_` ones) its folder
	 * pattern doesn't give. Whether a pattern's folders hold the right
	 * date is the move's to check, not this.
	 */
	public static function misplaced(ContentType $type, string $path, bool $landing): ?string
	{
		if ($type->keysByFolder() || $landing) {
			return null;
		}

		$below    = $type->folder === '' ? $path : substr($path, strlen($type->folder) + 1);
		$segments = explode('/', $below);
		$file     = (string) array_pop($segments);

		if ($segments !== [] && str_starts_with($file, 'index.')) {
			return 'is a folder entry; its type\'s entries are files';
		}

		// A hidden file stays where it is (D-629).
		if (str_starts_with($file, '_') || array_any($segments, static fn (string $segment): bool => str_starts_with($segment, '_'))) {
			return null;
		}

		$kept = $segments;

		return match (true) {
			$type->folders === null && $kept !== []           => 'is in a folder below its type\'s, which keeps its files directly in its folder',
			$type->folders !== null && ! $type->folders->explains($kept) => sprintf('is in a folder its type\'s folder pattern (%s) doesn\'t give', $type->folders->pattern),
			default                                           => null
		};
	}

	/**
	 * Returns the files not in their folder: where each belongs, by path.
	 *
	 * @return array<string, string>
	 */
	public function report(): array
	{
		return array_map(static fn (array $moves): string => (string) array_first($moves), $this->plans());
	}

	/**
	 * Moves every file not in its folder there, or only those `$allowed`
	 * passes (by path).
	 *
	 * @param ?Closure(string): bool $allowed
	 */
	public function move(?Closure $allowed = null): RenamedFiles
	{
		$moves = array_filter($this->plans(), static fn (string $path): bool => $allowed === null || $allowed($path), ARRAY_FILTER_USE_KEY);

		return $this->writer->renameFiles($moves);
	}

	/**
	 * Moves one entry to the folder its type's folder pattern gives it
	 * after its date or slug changed (D-519's way). Returns the entry's
	 * new path, or `null` when it stays: its type has no pattern, it's
	 * already there, it's left alone, or the path is taken.
	 */
	public function follow(string $path): ?string
	{
		$snapshot = $this->index->snapshot();
		$record   = $snapshot->records[$path] ?? null;
		$type     = $record === null ? null : $this->types->find($record['type']);

		if ($type?->folders === null) {
			return null;
		}

		$moves = $this->plan($snapshot, $path);

		return $moves === null ? null : $this->writer->renameFiles([$path => $moves])->renamed[$path] ?? null;
	}

	/**
	 * Returns what moves for each entry not in its folder, by path.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function plans(): array
	{
		$this->indexer->index();

		$snapshot = $this->index->snapshot();
		$plans    = [];

		foreach (array_keys($snapshot->records) as $path) {
			$moves = $this->plan($snapshot, (string) $path);

			if ($moves !== null) {
				$plans[(string) $path] = $moves;
			}
		}

		return $plans;
	}

	/**
	 * Returns what moves to put an entry's file in its folder: its own,
	 * and each translation linked by name, or `null` when it's there,
	 * it's left alone, or it's a translation that moves with its
	 * original.
	 *
	 * @return ?array<string, string>
	 */
	private function plan(IndexSnapshot $snapshot, string $path): ?array
	{
		$record = $snapshot->records[$path] ?? null;
		$type   = $record === null ? null : $this->types->find($record['type']);

		if ($record === null || $type === null || $type->keysByFolder() || $record['landing']) {
			return null;
		}

		// A translation linked by name moves with its original.
		if ($record['original'] !== null && $record['group'] === null && isset($snapshot->records[$record['original']])) {
			return null;
		}

		$date = $type->folders?->isDated() === true
			? $this->dates->orUpdated($path, $type->name, $record['updated'])
			: DateTimeImmutable::createFromTimestamp($record['updated']);

		if (is_string($date)) {
			return null;
		}

		$directory = self::isHidden($type, $path) ? self::directoryOf($path) : $type->directoryFor($record['slug'], $date);
		$target    = ltrim("{$directory}/" . self::fileName($path), '/');

		if ($target === $path) {
			return null;
		}

		$moves = [$path => $target];

		foreach ($snapshot->translations($path) as $translation) {
			$linked = $snapshot->records[$translation] ?? null;

			if ($translation !== $path && $linked !== null && $linked['group'] === null) {
				$moves[$translation] = ltrim("{$directory}/" . self::fileName($translation), '/');
			}
		}

		return $moves;
	}

	/**
	 * Returns whether a file is hidden: `_`-named, or in a `_` folder
	 * below its type's (a folder entry's own folder included).
	 */
	private static function isHidden(ContentType $type, string $path): bool
	{
		$below = $type->folder === '' ? $path : substr($path, strlen($type->folder) + 1);

		return array_any(explode('/', $below), static fn (string $segment): bool => str_starts_with($segment, '_'));
	}

	/**
	 * Returns the folder a file is listed in: its own, or a folder
	 * entry's folder's.
	 */
	private static function directoryOf(string $path): string
	{
		$directory = dirname($path);

		if (str_starts_with(basename($path), 'index.') && $directory !== '.') {
			$directory = dirname($directory);
		}

		return $directory === '.' ? '' : $directory;
	}

	/**
	 * Returns the name a file keeps when it moves: its own, or a folder
	 * entry's folder's (`hello/index.fr.md` is `hello.fr.md`).
	 */
	private static function fileName(string $path): string
	{
		$file = basename($path);

		return str_contains($path, '/') && str_starts_with($file, 'index.')
			? basename(dirname($path)) . substr($file, strlen('index'))
			: $file;
	}
}
