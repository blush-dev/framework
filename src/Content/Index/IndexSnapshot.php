<?php

/**
 * Index snapshot.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Index;

use Blush\Content\EntryFields;
use Blush\Content\Relation\LinkReport;
use Blush\Content\Relation\RelationGraph;
use Blush\Content\Status;
use Blush\Support\Uuid;

/**
 * The whole content index at one moment: every record, plus lookups
 * derived from them when the index is built, so reading needs no work:
 *
 * - `keys` finds an entry by language, type, and key (D-036, D-455:
 *   each language's entries have their own keys). When two files claim
 *   one key, a bundle's `index` file wins over a plain file (1.x's page
 *   lookup order), then a file without a language suffix over one with
 *   the default language's (`about.md` over `about.en.md`), and the
 *   clash is kept in `conflicts` for `content:lint`.
 * - `terms` is the reverse of each record's terms: the entries that
 *   reference each term, by taxonomy and slug.
 * - `labels` keeps the first label written for each term.
 * - `children` is the reverse of each record's parent: the entries
 *   under each parent, by language, type, and parent key.
 * - `translations` links each file to its translations (D-455): the
 *   entries of each translation group (`IndexRecord::groupOf()`, which
 *   links a plain file with a bundle, D-460), by language. A
 *   translation whose `translation_of` names its original's id joins the
 *   original's group whatever its name (D-511; `translationOf()`).
 * - A translation's key and parent are put in its language first
 *   (D-457): each folder above it that has a translation in the
 *   language takes that translation's key, and a parent named by its
 *   original's key takes its translation's, so `about/biography.fr.md`
 *   is `a-propos/biographie` when `about/index.fr.md` is `a-propos`.
 * - `ids` finds an entry by its id (D-477). When files share one (a
 *   copied file), the first by path holds it, and every file sharing it
 *   is kept in `duplicates` for `content:lint` and `content:ids`.
 * - `links` is the relation graph (D-585, D-590): every link between
 *   entries by id, forward and reverse (`graph()`). It's set by
 *   `withLinks()`, which also puts each record's `terms` in the form
 *   relations resolve them to, and `terms` from those.
 * - `scheduled` is the earliest publish time still to come when the
 *   index was built, for cache invalidation.
 * - `fingerprint` identifies what the records were built with (content
 *   types, timezone, locale). A different one forces a full rebuild.
 * - `rows` are the entries and refs as the record layer keeps them
 *   (`SnapshotRecords`, D-649), built when the index is, so a request
 *   only reads them.
 *
 * @phpstan-import-type RecordArray from IndexRecord
 * @phpstan-import-type GraphArray from RelationGraph
 * @phpstan-type RowArray array{id: string, fields: array<string, mixed>, content?: ?string, version?: ?string}
 * @phpstan-type RowsArray array{entries: list<RowArray>, refs: list<RowArray>}
 * @phpstan-type SnapshotArray array{
 *     version: int,
 *     fingerprint: string,
 *     built: int,
 *     records: array<string, RecordArray>,
 *     keys: array<string, array<string, array<string, string>>>,
 *     terms: array<string, array<string, list<string>>>,
 *     labels: array<string, array<string, string>>,
 *     children: array<string, array<string, array<string, list<string>>>>,
 *     translations: array<string, array<string, string>>,
 *     conflicts: array<string, list<string>>,
 *     scheduled: ?int,
 *     ids: array<string, string>,
 *     duplicates: array<string, list<string>>,
 *     links: GraphArray,
 *     rows: ?RowsArray
 * }
 */
final readonly class IndexSnapshot
{
	/**
	 * The index format's version. A stored index with another version is
	 * rebuilt.
	 */
	public const int VERSION = 11;

	/**
	 * @param array<string, RecordArray>                                $records   Keyed by path, sorted by path.
	 * @param array<string, array<string, array<string, string>>>       $keys      Paths by language, type, and key.
	 * @param array<string, array<string, list<string>>>                $terms     Referencing paths by taxonomy and slug.
	 * @param array<string, array<string, string>>                      $labels    Term labels by taxonomy and slug.
	 * @param array<string, array<string, array<string, list<string>>>> $children  Paths by language, type, and parent key.
	 * @param array<string, list<string>>                               $conflicts Paths claiming one `language/type/key`.
	 * @param array<string, array<string, string>>                      $translations Paths by translation group and language.
	 * @param array<string, string>                                     $ids          Paths by id.
	 * @param array<string, list<string>>                               $duplicates   Paths sharing each id held by more than one.
	 * @param ?GraphArray                                               $links        The relation graph, or `null` for none.
	 * @param ?RowsArray                                                $rows         The entries and refs as records, or `null` before they're built.
	 */
	private function __construct(
		public string $fingerprint,
		public int $built,
		public array $records,
		public array $keys,
		public array $terms,
		public array $labels,
		public array $children,
		public array $conflicts,
		public ?int $scheduled,
		public array $translations = [],
		public array $ids = [],
		public array $duplicates = [],
		private ?array $links = null,
		private ?array $rows = null
	) {}

	/**
	 * Returns an index with no records.
	 */
	public static function empty(): self
	{
		return new self('', 0, [], [], [], [], [], [], null);
	}

	/**
	 * Builds a snapshot from records, deriving the lookups.
	 *
	 * @param iterable<IndexRecord> $records
	 */
	public static function build(iterable $records, string $fingerprint, int $built): self
	{
		$byPath = [];

		foreach ($records as $record) {
			$byPath[$record->path] = $record->toArray();
		}

		ksort($byPath, SORT_STRING);

		$byPath = new TranslatedKeys(self::linkedById($byPath))->records();

		$keys      = [];
		$claims    = [];
		$labels    = [];
		$children  = [];
		$scheduled = null;
		$linked    = [];
		$ids       = [];

		foreach ($byPath as $path => $record) {
			$language = $record['language'];

			$claims["{$language}/{$record['type']}/{$record['key']}"][] = $path;

			$current = $keys[$language][$record['type']][$record['key']] ?? null;

			if ($current === null || self::wins($record, $byPath[$current])) {
				$keys[$language][$record['type']][$record['key']] = $path;
			}

			$linked[IndexRecord::groupOf($record)][$language][] = $path;

			if ($record['id'] !== null) {
				$ids[$record['id']][] = $path;
			}

			if ($record['parent'] !== null) {
				$children[$language][$record['type']][$record['parent']][] = $path;
			}

			foreach ($record['labels'] as $taxonomy => $termLabels) {
				$labels[$taxonomy] = ($labels[$taxonomy] ?? []) + $termLabels;
			}

			if ($record['status'] === Status::Published->value && $record['published'] !== null && $record['published'] > $built) {
				$scheduled = min($scheduled ?? PHP_INT_MAX, $record['published']);
			}
		}

		$translations = [];

		foreach ($linked as $group => $languages) {
			if (count($languages) > 1) {
				foreach ($languages as $language => $paths) {
					$translations[$group][$language] = self::preferred($paths, $byPath);
				}
			}
		}

		return new self(
			$fingerprint,
			$built,
			$byPath,
			$keys,
			self::reverseTerms($byPath),
			$labels,
			$children,
			array_filter($claims, static fn (array $paths): bool => count($paths) > 1),
			$scheduled,
			$translations,
			array_map(static fn (array $paths): string => $paths[0], $ids),
			array_filter($ids, static fn (array $paths): bool => count($paths) > 1)
		);
	}

	/**
	 * Returns what a record's `translation_of` (D-511) links it to: the
	 * path of the entry it names and `null`, or `null` and why it links
	 * to nothing, finishing a sentence. Both are `null` for a record
	 * without one. It names an original: a file of the same type
	 * without a language suffix, in another language.
	 *
	 * @param  RecordArray                $record
	 * @param  array<string, string>      $ids     Paths by id.
	 * @param  array<string, RecordArray> $records Records by path.
	 * @return array{?string, ?string}
	 */
	public static function translationOf(array $record, array $ids, array $records): array
	{
		$id = $record['values'][EntryFields::TRANSLATION_OF] ?? null;

		if (! is_string($id) || ! Uuid::isValid($id) || $record['original'] === null) {
			return [null, null];
		}

		$path   = $ids[strtolower($id)] ?? null;
		$target = $path === null ? null : $records[$path] ?? null;

		return match (true) {
			$path === null || $target === null           => [null, 'names no entry\'s id.'],
			$path === $record['path']                    => [null, 'names the file\'s own id.'],
			$target['type'] !== $record['type']          => [null, sprintf('names %s, which is another type\'s.', $path)],
			$target['original'] !== null                 => [null, sprintf('names %s, a translation; name its original instead.', $path)],
			$target['language'] === $record['language'] => [null, sprintf('names %s, which is in the same language.', $path)],
			default                                      => [$path, null]
		};
	}

	/**
	 * Returns the records with each translation's `group` set from its
	 * `translation_of` (D-511), and every other's cleared, since a group
	 * depends on another file.
	 *
	 * @param  array<string, RecordArray> $records
	 * @return array<string, RecordArray>
	 */
	private static function linkedById(array $records): array
	{
		$ids = [];

		foreach ($records as $path => $record) {
			if ($record['id'] !== null) {
				$ids[$record['id']] ??= $path;
			}
		}

		foreach ($records as $path => $record) {
			[$original] = self::translationOf($record, $ids, $records);

			$records[$path]['group'] = $original === null ? null : IndexRecord::groupOf([...$records[$original], 'group' => null]);
		}

		return $records;
	}

	/**
	 * Returns the earliest publish time after `$now` of an entry that
	 * isn't a draft, or `null` when nothing is scheduled. The content
	 * version changes at that time (D-128).
	 */
	public function nextScheduled(int $now): ?int
	{
		$next = null;

		foreach ($this->records as $record) {
			if ($record['status'] === Status::Published->value && $record['published'] !== null && $record['published'] > $now) {
				$next = min($next ?? PHP_INT_MAX, $record['published']);
			}
		}

		return $next;
	}

	/**
	 * Returns whether the snapshot has no records.
	 */
	public function isEmpty(): bool
	{
		return $this->records === [];
	}

	/**
	 * Returns a record by path.
	 */
	public function record(string $path): ?IndexRecord
	{
		return isset($this->records[$path]) ? IndexRecord::fromArray($this->records[$path]) : null;
	}

	/**
	 * Returns the path of the entry with an id, or `null`.
	 */
	public function path(string $id): ?string
	{
		return $this->ids[strtolower($id)] ?? null;
	}

	/**
	 * Returns the path of the entry with a key in a type and language.
	 */
	public function find(string $language, string $type, string $key): ?string
	{
		return $this->keys[$language][$type][trim($key, '/')] ?? null;
	}

	/**
	 * Returns whether a type has an entry with a key in any language: a
	 * term or profile named by slug is one only when it has a file
	 * (D-584).
	 */
	public function has(string $type, string $key): bool
	{
		return array_any($this->keys, static fn (array $types): bool => isset($types[$type][trim($key, '/')]));
	}

	/**
	 * Returns the paths of an entry and its translations, by language
	 * code, or `[]` when it has none.
	 *
	 * @return array<string, string>
	 */
	public function translations(string $path): array
	{
		$record = $this->records[$path] ?? null;

		return $record === null ? [] : $this->translations[IndexRecord::groupOf($record)] ?? [];
	}

	/**
	 * Returns the relation graph: every link between entries (D-585).
	 */
	public function graph(): RelationGraph
	{
		return $this->links === null ? RelationGraph::empty() : RelationGraph::fromArray($this->links);
	}

	/**
	 * Returns a copy with relations resolved (D-590): the graph, and each
	 * record's `terms` replaced by the ones relations give, for the
	 * records they were resolved for (entries with an id), with `terms`
	 * worked out from them again.
	 */
	public function withLinks(LinkReport $report): self
	{
		$records = $this->records;

		foreach ($report->terms as $path => $terms) {
			if (isset($records[$path])) {
				$records[$path]['terms'] = $terms;
			}
		}

		return clone($this, ['records' => $records, 'terms' => self::reverseTerms($records), 'links' => $report->graph->toArray()]);
	}

	/**
	 * Returns the paths of the entries that reference a term.
	 *
	 * @return list<string>
	 */
	public function referencing(string $taxonomy, string $slug): array
	{
		return $this->terms[$taxonomy][$slug] ?? [];
	}

	/**
	 * Returns the paths of the entries whose parent is a key in a type and
	 * language.
	 *
	 * @return list<string>
	 */
	public function children(string $language, string $type, string $key): array
	{
		return $this->children[$language][$type][$key] ?? [];
	}

	/**
	 * Returns every referenced slug of a taxonomy with its label, which is
	 * the slug unless the term was written differently, whether or not
	 * the term has a file; `content:terms` titles the files it writes
	 * with them (D-584).
	 *
	 * @return array<string, string>
	 */
	public function termLabels(string $taxonomy): array
	{
		$labels = [];

		foreach (array_keys($this->terms[$taxonomy] ?? []) as $slug) {
			$labels[$slug] = $this->labels[$taxonomy][$slug] ?? (string) $slug;
		}

		return $labels;
	}

	/**
	 * Returns the snapshot with its entries and refs as records.
	 *
	 * @param RowsArray $rows
	 */
	public function withRows(array $rows): self
	{
		return clone($this, ['rows' => $rows]);
	}

	/**
	 * Returns the entries and refs as records, or `null` before they're
	 * built.
	 *
	 * @return ?RowsArray
	 */
	public function rows(): ?array
	{
		return $this->rows;
	}

	/**
	 * Returns the snapshot as an array for storage.
	 *
	 * @return SnapshotArray
	 */
	public function toArray(): array
	{
		return [
			'version'      => self::VERSION,
			'fingerprint'  => $this->fingerprint,
			'built'        => $this->built,
			'records'      => $this->records,
			'keys'         => $this->keys,
			'terms'        => $this->terms,
			'labels'       => $this->labels,
			'children'     => $this->children,
			'translations' => $this->translations,
			'conflicts'    => $this->conflicts,
			'scheduled'    => $this->scheduled,
			'ids'          => $this->ids,
			'duplicates'   => $this->duplicates,
			'links'        => $this->links ?? RelationGraph::empty()->toArray(),
			'rows'         => $this->rows
		];
	}

	/**
	 * Rebuilds a snapshot from `toArray()`'s output. Data from another
	 * format version gives an empty snapshot, which forces a rebuild.
	 *
	 * @param array<array-key, mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		if (($data['version'] ?? null) !== self::VERSION) {
			return self::empty();
		}

		/** @var SnapshotArray $data Written by `toArray()`. */
		return new self(
			$data['fingerprint'],
			$data['built'],
			$data['records'],
			$data['keys'],
			$data['terms'],
			$data['labels'],
			$data['children'],
			$data['conflicts'],
			$data['scheduled'],
			$data['translations'],
			$data['ids'],
			$data['duplicates'],
			$data['links'],
			$data['rows']
		);
	}

	/**
	 * Returns the paths referencing each term, by taxonomy (or people key)
	 * and slug, from records' terms.
	 *
	 * @param  array<string, RecordArray> $records
	 * @return array<string, array<string, list<string>>>
	 */
	private static function reverseTerms(array $records): array
	{
		$terms = [];

		foreach ($records as $path => $record) {
			foreach ($record['terms'] as $taxonomy => $slugs) {
				foreach ($slugs as $slug) {
					$terms[$taxonomy][$slug][] = $path;
				}
			}
		}

		return $terms;
	}

	/**
	 * Returns whether a record wins a key over the one holding it: a
	 * bundle's `index` file over a plain file, then a file without a
	 * language suffix over one with the default language's.
	 *
	 * @param RecordArray $record
	 * @param RecordArray $current
	 */
	public static function wins(array $record, array $current): bool
	{
		$bundle = self::isBundle($record);

		return $bundle !== self::isBundle($current)
			? $bundle
			: $record['original'] === null && $current['original'] !== null;
	}

	/**
	 * Returns which of the paths one language claims a path with links as
	 * its translation: the one that would win its key.
	 *
	 * @param  non-empty-list<string>     $paths
	 * @param  array<string, RecordArray> $records
	 */
	public static function preferred(array $paths, array $records): string
	{
		return array_reduce($paths, static fn (string $best, string $path): string => self::wins($records[$path], $records[$best]) ? $path : $best, $paths[0]);
	}

	/**
	 * Returns whether a record is a bundle's `index` file, with or
	 * without a language suffix.
	 *
	 * @param RecordArray $record
	 */
	private static function isBundle(array $record): bool
	{
		return pathinfo($record['original'] ?? $record['path'], PATHINFO_FILENAME) === 'index';
	}
}
