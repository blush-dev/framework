<?php

/**
 * Translated keys.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Index;

/**
 * Puts each translation's key and parent in its language when a snapshot
 * is built (D-457). A record's own file gives its key from folder names
 * and its slug, and its parent as its original's key, which is all a
 * default-language entry needs. A translation's depend on other files:
 *
 * - Each folder above it whose entry has a translation in its language
 *   takes that translation's key, so `about/biography.fr.md` is
 *   `a-propos/biographie` when `about/index.fr.md` has `slug: a-propos`.
 *   A folder without one keeps its name.
 * - Its parent becomes the translated folder path for a tree, or for a
 *   hierarchical term (whose `parent` names the original's key), the
 *   parent's translation's key when there is one.
 *
 * The path's values are kept in `untranslated`, and a record that comes
 * with them (from an earlier snapshot) is put back first, so building
 * again from stored records gives the same keys.
 *
 * @phpstan-import-type RecordArray from IndexRecord
 */
final class TranslatedKeys
{
	/**
	 * Records by ID, with their path's keys and parents.
	 *
	 * @var array<string, RecordArray>
	 */
	private array $records = [];

	/**
	 * Default-language records' IDs by type and key: the entries
	 * translations name by their original keys.
	 *
	 * @var array<string, array<string, string>>
	 */
	private array $originals = [];

	/**
	 * Record IDs by translation group (`IndexRecord::groupOf()`) and
	 * language.
	 *
	 * @var array<string, array<string, non-empty-list<string>>>
	 */
	private array $linked = [];

	/**
	 * Keys and parents already put in their language, by ID.
	 *
	 * @var array<string, array{string, ?string}>
	 */
	private array $resolved = [];

	/**
	 * IDs being resolved, which guards against a loop.
	 *
	 * @var array<string, true>
	 */
	private array $busy = [];

	/**
	 * @param array<string, RecordArray> $records Keyed by ID.
	 */
	public function __construct(array $records)
	{
		foreach ($records as $id => $record) {
			if ($record['untranslated'] !== null) {
				$record = [...$record, 'key' => $record['untranslated']['key'], 'parent' => $record['untranslated']['parent'], 'untranslated' => null];
			}

			$this->records[$id]                                                = $record;
			$this->linked[IndexRecord::groupOf($record)][$record['language']][] = $id;
		}

		foreach ($this->records as $id => $record) {
			$current = $this->originals[$record['type']][$record['key']] ?? null;

			if ($record['original'] === null && ($current === null || IndexSnapshot::wins($record, $this->records[$current]))) {
				$this->originals[$record['type']][$record['key']] = $id;
			}
		}
	}

	/**
	 * Returns the records, each translation's key and parent in its
	 * language.
	 *
	 * @return array<string, RecordArray>
	 */
	public function records(): array
	{
		$records = $this->records;

		foreach ($records as $id => $record) {
			[$key, $parent] = $this->resolve($id);

			if ($key !== $record['key'] || $parent !== $record['parent']) {
				$records[$id] = [
					...$record,
					'key'          => $key,
					'parent'       => $parent,
					'untranslated' => ['key' => $record['key'], 'parent' => $record['parent']]
				];
			}
		}

		return $records;
	}

	/**
	 * Returns a record's key and parent in its language.
	 *
	 * @return array{string, ?string}
	 */
	private function resolve(string $id): array
	{
		$record = $this->records[$id];

		if (isset($this->resolved[$id])) {
			return $this->resolved[$id];
		}

		if ($record['original'] === null || $record['key'] === '' || isset($this->busy[$id])) {
			return [$record['key'], $record['parent']];
		}

		$this->busy[$id] = true;

		$folders = explode('/', $record['key']);
		$slug    = array_pop($folders);
		$path    = [];

		foreach ($folders as $index => $folder) {
			$above = $this->translation($record['type'], implode('/', array_slice($folders, 0, $index + 1)), $record['language']);
			$path  = $above === null || $above === $id ? [...$path, $folder] : explode('/', $this->resolve($above)[0]);
		}

		$parent = $record['parent'];

		if ($parent !== null && $folders !== [] && $parent === implode('/', $folders)) {
			$parent = implode('/', $path);
		} elseif ($parent !== null) {
			$above  = $this->translation($record['type'], $parent, $record['language']);
			$parent = $above === null || $above === $id ? $parent : $this->resolve($above)[0];
		}

		unset($this->busy[$id]);

		return $this->resolved[$id] = [implode('/', [...$path, $slug]), $parent];
	}

	/**
	 * Returns the ID of the translation, in a language, of the default
	 * language's entry with a key, or `null`.
	 */
	private function translation(string $type, string $key, string $language): ?string
	{
		$original = $this->originals[$type][$key] ?? null;
		$ids      = $original === null ? null : $this->linked[IndexRecord::groupOf($this->records[$original])][$language] ?? null;

		return $ids === null ? null : IndexSnapshot::preferred($ids, $this->records);
	}
}
