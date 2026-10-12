<?php

/**
 * Undeclared keys.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

use Blush\Content\Record\EntryTable;
use Blush\Content\Relation\Refs;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\WriteException;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordStores;

/**
 * Finds front matter keys nothing declares (D-786): no field of the
 * entry's type, Blush's, a plugin's, or a field set's. They're never read,
 * but they stay in their files until removed here, which Site Health
 * offers (asking first). An entry's `id` and `refs` aren't fields, and
 * aren't undeclared.
 *
 * Entries are read from the content records and changed by id through
 * `Entries` (D-654), so it works however content is kept.
 */
final readonly class UndeclaredKeys
{
	public function __construct(
		private RecordStores $stores,
		private Entries $content,
		private ContentTypes $types
	) {}

	/**
	 * Returns the entries with keys nothing declares, by id: each with its
	 * `title`, `type`, and the `keys`.
	 *
	 * @return array<string, array{title: string, type: string, keys: list<string>}>
	 */
	public function report(): array
	{
		$found = [];

		foreach ($this->stores->query(EntryTable::table())->withoutContent()->get() as $record) {
			$type  = self::text($record, 'type');
			$front = $record->fields['fields'] ?? null;
			$keys  = $this->keys($type, is_array($front) ? $front : []);

			if ($keys !== []) {
				$found[$record->id] = ['title' => self::text($record, 'title') ?: self::text($record, 'slug'), 'type' => $type, 'keys' => $keys];
			}
		}

		return $found;
	}

	/**
	 * Returns the keys in a type's front matter that nothing declares.
	 *
	 * @param  array<array-key, mixed> $front
	 * @return list<string>
	 */
	public function keys(string $type, array $front): array
	{
		if (! $this->types->has($type)) {
			return [];
		}

		$schema = $this->types->schema($type);
		$keys   = [];

		foreach (array_keys($front) as $key) {
			$key = (string) $key;

			if ($schema->field($key) === null && ! in_array($key, [EntryFields::ID, Refs::FIELD], true)) {
				$keys[] = $key;
			}
		}

		return $keys;
	}

	/**
	 * Removes the undeclared keys from each entry given (or every one the
	 * report lists). Returns the ids of the entries changed, and the ones
	 * that couldn't be with why.
	 *
	 * @param  ?list<string> $ids
	 * @return array{changed: list<string>, failed: array<string, string>}
	 */
	public function remove(?array $ids = null): array
	{
		$changed = [];
		$failed  = [];

		foreach ($ids ?? array_map(strval(...), array_keys($this->report())) as $id) {
			$found = $this->content->find($id);

			if ($found === null) {
				$failed[$id] = 'No entry has this id.';
				continue;
			}

			try {
				$keys = $this->keys($found->type->name, $this->content->editable($id)->frontMatter);

				if ($keys !== []) {
					$changed[] = $this->content->change($id, new EntryChanges(remove: $keys))->id ?? $id;
				}
			} catch (WriteException $e) {
				$failed[$id] = $e->getMessage();
			}
		}

		return ['changed' => $changed, 'failed' => $failed];
	}

	/**
	 * Returns a record's text field, or `''`.
	 */
	private static function text(Record $record, string $field): string
	{
		$value = $record->fields[$field] ?? null;

		return is_string($value) ? $value : '';
	}
}
