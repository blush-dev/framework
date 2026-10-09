<?php

/**
 * Entry targets.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

use Override;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Record\EntryRecords;
use Blush\Core\AppConfig;

/**
 * Finds targets through `Entries` and the `entries` records (D-589,
 * D-654), whatever store keeps them. A written value is a key (a slug,
 * or a tree's path), found in the source's language first, since each
 * language has its own keys (D-457), then in any. Links point at
 * originals, so a translation found that way answers its original's id
 * (its `original_id`). Entries without an id aren't targets: a link is
 * between ids.
 */
final readonly class EntryTargets implements TargetLookup
{
	public function __construct(
		private Entries $content,
		private EntryRecords $records,
		private AppConfig $app
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function find(string $type, string $written, string $language): ?string
	{
		$entry = $this->content->named($type, $written, $language);

		foreach ($entry === null ? array_keys($this->app->languages->all()) : [] as $other) {
			$entry ??= $this->content->named($type, $written, (string) $other);
		}

		return $entry === null ? null : $this->originalOf($entry);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function typeOf(string $id): ?string
	{
		$record = $this->records->find($id);

		return $record === null ? null : EntryRecords::text($record, 'type');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function written(string $id): ?string
	{
		$original = $this->original($id);

		return $original === null ? null : $this->content->find($original)?->key;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function original(string $id): ?string
	{
		$record = $this->records->find($id);

		if ($record === null) {
			return null;
		}

		$original = $record->fields['original_id'] ?? null;

		return is_string($original) ? $original : $record->id;
	}

	/**
	 * Returns the id of an entry's original: its own unless it's a
	 * translation, the original's (which has an id) for one that is.
	 */
	private function originalOf(Entry $entry): ?string
	{
		if ($entry->id !== null) {
			return $this->original($entry->id);
		}

		return $this->app->languages->isDefault($entry->language)
			? null
			: $this->content->translation($entry, $this->app->languages->default->code)?->id;
	}
}
