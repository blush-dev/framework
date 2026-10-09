<?php

/**
 * Index locations.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Index;

use Override;
use WeakMap;
use Blush\Content\Record\EntryLocations;
use Blush\Content\Record\EntryPlaces;
use Blush\Content\Type\ContentTypes;

/**
 * The filesystem driver's entry locations: keys and folders from its
 * index's records, by parents as every driver's are (`EntryPlaces`,
 * D-656), and each entry's file. They follow the index as it's brought
 * up to date, worked out once for each version of it.
 */
final class IndexLocations implements EntryLocations
{
	/**
	 * Places worked out, by the records they're from.
	 *
	 * @var WeakMap<SnapshotRecords, array{places: array<string, array{key: string, folder: string}>, byKey: array<string, list<string>>, byFolder: array<string, list<string>>}>
	 */
	private WeakMap $known;

	public function __construct(
		private readonly IndexFreshness $freshness,
		private readonly ContentTypes $types
	) {
		$this->known = new WeakMap();
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function idsIn(array $folders): array
	{
		$byFolder = $this->places()['byFolder'];

		return array_values(array_unique(array_merge(...array_map(static fn (string $folder): array => $byFolder[$folder] ?? [], $folders))));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function idsWithKey(string $key): array
	{
		return $this->places()['byKey'][$key] ?? [];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function key(string $id): ?string
	{
		return $this->places()['places'][strtolower($id)]['key'] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function path(string $id): string
	{
		return $this->freshness->fresh()->records()->path($id) ?? '';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function refresh(): void
	{
		// The index is brought up to date by its writes.
	}

	/**
	 * Returns the current records' places, with ids by key and by folder.
	 *
	 * @return array{places: array<string, array{key: string, folder: string}>, byKey: array<string, list<string>>, byFolder: array<string, list<string>>}
	 */
	private function places(): array
	{
		$records = $this->freshness->fresh()->records();

		if (isset($this->known[$records])) {
			return $this->known[$records];
		}

		$rows = [];

		foreach ($records->entries as $row) {
			$fields = $row['fields'];

			$rows[$row['id']] = [
				'type'      => is_string($fields['type'] ?? null) ? $fields['type'] : '',
				'slug'      => is_string($fields['slug'] ?? null) ? $fields['slug'] : '',
				'parent_id'   => is_string($fields['parent_id'] ?? null) ? $fields['parent_id'] : null,
				'language'    => is_string($fields['language'] ?? null) ? $fields['language'] : '',
				'original_id' => is_string($fields['original_id'] ?? null) ? $fields['original_id'] : null,
				'archive'     => is_string($fields['archive'] ?? null) ? $fields['archive'] : null
			];
		}

		$places   = EntryPlaces::of($rows, $this->types);
		$byKey    = [];
		$byFolder = [];

		foreach ($places as $id => $place) {
			$byKey[$place['key']][]       = $id;
			$byFolder[$place['folder']][] = $id;
		}

		return $this->known[$records] = ['places' => $places, 'byKey' => $byKey, 'byFolder' => $byFolder];
	}
}
