<?php

/**
 * Record locations.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Record;

use Override;
use Blush\Content\Type\ContentTypes;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordStores;

/**
 * Entry locations read from the `entries` table alone, for a store that
 * keeps no files (`EntryPlaces`). Rows are read once, on the first
 * question, and again after `refresh()`.
 */
final class RecordLocations implements EntryLocations
{
	/**
	 * Each entry's key and folder, by id, once read.
	 *
	 * @var ?array<string, array{key: string, folder: string}>
	 */
	private ?array $places = null;

	public function __construct(
		private readonly RecordStores $stores,
		private readonly ContentTypes $types
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function idsIn(array $folders): array
	{
		return array_keys(array_filter($this->places(), static fn (array $place): bool => in_array($place['folder'], $folders, true)));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function idsWithKey(string $key): array
	{
		return array_keys(array_filter($this->places(), static fn (array $place): bool => $place['key'] === $key));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function key(string $id): ?string
	{
		return $this->places()[strtolower($id)]['key'] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function path(string $id): string
	{
		return '';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function refresh(): void
	{
		$this->places = null;
	}

	/**
	 * Returns each entry's key and folder, by id.
	 *
	 * @return array<string, array{key: string, folder: string}>
	 */
	private function places(): array
	{
		if ($this->places !== null) {
			return $this->places;
		}

		$table = EntryTable::table();
		$rows  = [];

		foreach ($this->stores->store($table)->select($table, new RecordQuery()->withoutContent())->records as $record) {
			$parent   = EntryRecords::text($record, 'parent_id');
			$original = EntryRecords::text($record, 'original_id');
			$archive  = EntryRecords::text($record, 'archive');

			$rows[$record->id] = [
				'type'        => EntryRecords::text($record, 'type'),
				'slug'        => EntryRecords::text($record, 'slug'),
				'parent_id'   => $parent === '' ? null : $parent,
				'language'    => EntryRecords::text($record, 'language'),
				'original_id' => $original === '' ? null : $original,
				'archive'     => $archive === '' ? null : $archive
			];
		}

		return $this->places = EntryPlaces::of($rows, $this->types);
	}
}
