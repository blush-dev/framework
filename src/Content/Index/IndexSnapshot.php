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

use Blush\Content\Status;

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
 *   links a plain file with a bundle, D-460), by language.
 * - A translation's key and parent are put in its language first
 *   (D-457): each folder above it that has a translation in the
 *   language takes that translation's key, and a parent named by its
 *   original's key takes its translation's, so `about/biography.fr.md`
 *   is `a-propos/biographie` when `about/index.fr.md` is `a-propos`.
 * - `scheduled` is the earliest publish time still to come when the
 *   index was built, for cache invalidation.
 * - `fingerprint` identifies what the records were built with (content
 *   types, timezone, locale). A different one forces a full rebuild.
 *
 * @phpstan-import-type RecordArray from IndexRecord
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
 *     scheduled: ?int
 * }
 */
final readonly class IndexSnapshot
{
	/**
	 * The index format's version. A stored index with another version is
	 * rebuilt.
	 */
	public const int VERSION = 5;

	/**
	 * @param array<string, RecordArray>                                $records   Keyed by ID, sorted by ID.
	 * @param array<string, array<string, array<string, string>>>       $keys      IDs by language, type, and key.
	 * @param array<string, array<string, list<string>>>                $terms     Referencing IDs by taxonomy and slug.
	 * @param array<string, array<string, string>>                      $labels    Term labels by taxonomy and slug.
	 * @param array<string, array<string, array<string, list<string>>>> $children  IDs by language, type, and parent key.
	 * @param array<string, list<string>>                               $conflicts IDs claiming one `language/type/key`.
	 * @param array<string, array<string, string>>                      $translations IDs by translation group and language.
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
		public array $translations = []
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
		$byId = [];

		foreach ($records as $record) {
			$byId[$record->id] = $record->toArray();
		}

		ksort($byId, SORT_STRING);

		$byId = new TranslatedKeys($byId)->records();

		$keys      = [];
		$claims    = [];
		$terms     = [];
		$labels    = [];
		$children  = [];
		$scheduled = null;
		$linked    = [];

		foreach ($byId as $id => $record) {
			$language = $record['language'];

			$claims["{$language}/{$record['type']}/{$record['key']}"][] = $id;

			$current = $keys[$language][$record['type']][$record['key']] ?? null;

			if ($current === null || self::wins($record, $byId[$current])) {
				$keys[$language][$record['type']][$record['key']] = $id;
			}

			$linked[IndexRecord::groupOf($record)][$language][] = $id;

			foreach ($record['terms'] as $taxonomy => $slugs) {
				foreach ($slugs as $slug) {
					$terms[$taxonomy][$slug][] = $id;
				}
			}

			if ($record['parent'] !== null) {
				$children[$language][$record['type']][$record['parent']][] = $id;
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
				foreach ($languages as $language => $ids) {
					$translations[$group][$language] = self::preferred($ids, $byId);
				}
			}
		}

		return new self(
			$fingerprint,
			$built,
			$byId,
			$keys,
			$terms,
			$labels,
			$children,
			array_filter($claims, static fn (array $ids): bool => count($ids) > 1),
			$scheduled,
			$translations
		);
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
	 * Returns a record by ID.
	 */
	public function record(string $id): ?IndexRecord
	{
		return isset($this->records[$id]) ? IndexRecord::fromArray($this->records[$id]) : null;
	}

	/**
	 * Returns the ID of the entry with a key in a type and language.
	 */
	public function find(string $language, string $type, string $key): ?string
	{
		return $this->keys[$language][$type][trim($key, '/')] ?? null;
	}

	/**
	 * Returns the IDs of an entry and its translations, by language
	 * code, or `[]` when it has none.
	 *
	 * @return array<string, string>
	 */
	public function translations(string $id): array
	{
		$record = $this->records[$id] ?? null;

		return $record === null ? [] : $this->translations[IndexRecord::groupOf($record)] ?? [];
	}

	/**
	 * Returns the IDs of the entries that reference a term.
	 *
	 * @return list<string>
	 */
	public function referencing(string $taxonomy, string $slug): array
	{
		return $this->terms[$taxonomy][$slug] ?? [];
	}

	/**
	 * Returns the IDs of the entries whose parent is a key in a type and
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
	 * the slug unless the term was written differently.
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
			'scheduled'    => $this->scheduled
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
			$data['translations']
		);
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
	 * Returns which of the IDs one language claims a path with links as
	 * its translation: the one that would win its key.
	 *
	 * @param  non-empty-list<string>     $ids
	 * @param  array<string, RecordArray> $records
	 */
	public static function preferred(array $ids, array $records): string
	{
		return array_reduce($ids, static fn (string $best, string $id): string => self::wins($records[$id], $records[$best]) ? $id : $best, $ids[0]);
	}

	/**
	 * Returns whether a record is a bundle's `index` file, with or
	 * without a language suffix.
	 *
	 * @param RecordArray $record
	 */
	private static function isBundle(array $record): bool
	{
		return pathinfo($record['original'] ?? $record['id'], PATHINFO_FILENAME) === 'index';
	}
}
