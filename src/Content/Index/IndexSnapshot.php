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
 * - `keys` finds an entry by locale, type, and key (D-036: index keys
 *   include the locale). When two files claim one key, a bundle's
 *   `index` file wins over a plain file (1.x's page lookup order), and
 *   the clash is kept in `conflicts` for `content:lint`.
 * - `terms` is the reverse of each record's terms: the entries that
 *   reference each term, by taxonomy and slug.
 * - `labels` keeps the first label written for each term.
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
	public const int VERSION = 1;

	/**
	 * @param array<string, RecordArray>                            $records   Keyed by ID, sorted by ID.
	 * @param array<string, array<string, array<string, string>>>   $keys      IDs by locale, type, and key.
	 * @param array<string, array<string, list<string>>>            $terms     Referencing IDs by taxonomy and slug.
	 * @param array<string, array<string, string>>                  $labels    Term labels by taxonomy and slug.
	 * @param array<string, list<string>>                           $conflicts IDs claiming one `locale/type/key`.
	 */
	private function __construct(
		public string $fingerprint,
		public int $built,
		public array $records,
		public array $keys,
		public array $terms,
		public array $labels,
		public array $conflicts,
		public ?int $scheduled
	) {}

	/**
	 * Returns an index with no records.
	 */
	public static function empty(): self
	{
		return new self('', 0, [], [], [], [], [], null);
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

		$keys      = [];
		$claims    = [];
		$terms     = [];
		$labels    = [];
		$scheduled = null;

		foreach ($byId as $id => $record) {
			$claims["{$record['locale']}/{$record['type']}/{$record['key']}"][] = $id;

			$current = $keys[$record['locale']][$record['type']][$record['key']] ?? null;

			if ($current === null || (! self::isBundle($current) && self::isBundle($id))) {
				$keys[$record['locale']][$record['type']][$record['key']] = $id;
			}

			foreach ($record['terms'] as $taxonomy => $slugs) {
				foreach ($slugs as $slug) {
					$terms[$taxonomy][$slug][] = $id;
				}
			}

			foreach ($record['labels'] as $taxonomy => $termLabels) {
				$labels[$taxonomy] = ($labels[$taxonomy] ?? []) + $termLabels;
			}

			if ($record['status'] === Status::Published->value && $record['published'] !== null && $record['published'] > $built) {
				$scheduled = min($scheduled ?? PHP_INT_MAX, $record['published']);
			}
		}

		return new self(
			$fingerprint,
			$built,
			$byId,
			$keys,
			$terms,
			$labels,
			array_filter($claims, static fn (array $ids): bool => count($ids) > 1),
			$scheduled
		);
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
	 * Returns the ID of the entry with a key in a type and locale.
	 */
	public function find(string $locale, string $type, string $key): ?string
	{
		return $this->keys[$locale][$type][trim($key, '/')] ?? null;
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
			'version'     => self::VERSION,
			'fingerprint' => $this->fingerprint,
			'built'       => $this->built,
			'records'     => $this->records,
			'keys'        => $this->keys,
			'terms'       => $this->terms,
			'labels'      => $this->labels,
			'conflicts'   => $this->conflicts,
			'scheduled'   => $this->scheduled
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
			$data['conflicts'],
			$data['scheduled']
		);
	}

	/**
	 * Returns whether an ID is a bundle's `index` file.
	 */
	private static function isBundle(string $id): bool
	{
		return pathinfo($id, PATHINFO_FILENAME) === 'index';
	}
}
