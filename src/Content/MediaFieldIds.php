<?php

/**
 * Media field ids.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

use Blush\Content\Record\EntryTable;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\WriteException;
use Blush\Field\Field;
use Blush\Field\Fields\ListField;
use Blush\Field\Fields\MediaField;
use Blush\Field\Fields\ObjectField;
use Blush\Media\MediaException;
use Blush\Media\MediaValues;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordStores;

/**
 * Finds media fields still written as paths (D-773), and rewrites each to
 * its file's id: a featured image, an avatar, a set's or a type's media
 * fields, lists of them, and the ones inside groups. A path to one of an
 * image's sizes becomes its original's id. A value that names no file in
 * the library, or one without an id yet (`media:ids`), is left as it is
 * and reported.
 *
 * Entries are read from the content records and changed by id through
 * `Entries` (D-654), so it works however content is kept.
 */
final readonly class MediaFieldIds
{
	public function __construct(
		private RecordStores $stores,
		private Entries $content,
		private ContentTypes $types,
		private MediaValues $media
	) {}

	/**
	 * Returns the entries with a media field written as a path, by id:
	 * each with its `title`, `type`, and `values` (where each is, as
	 * `image` or `rights.photo` or `gallery.2`, the `path`, and the `id`
	 * it becomes, `null` when it can't).
	 *
	 * @return array<string, array{title: string, type: string, values: list<array{field: string, path: string, id: ?string}>}>
	 * @throws MediaException
	 */
	public function report(): array
	{
		$found = [];

		foreach ($this->stores->query(EntryTable::table())->withoutContent()->get() as $record) {
			$type = self::text($record, 'type');

			if (! $this->types->has($type)) {
				continue;
			}

			$front  = $record->fields['fields'] ?? null;
			$values = $this->paths($type, is_array($front) ? $front : []);

			if ($values !== []) {
				$found[$record->id] = ['title' => self::text($record, 'title') ?: self::text($record, 'slug'), 'type' => $type, 'values' => $values];
			}
		}

		return $found;
	}

	/**
	 * Returns the media values written as paths in an entry's values, by
	 * its type: where each is, the path, and the id it becomes.
	 *
	 * @param  array<array-key, mixed> $values
	 * @return list<array{field: string, path: string, id: ?string}>
	 * @throws MediaException
	 */
	public function paths(string $type, array $values): array
	{
		$found = [];

		if ($this->types->has($type)) {
			$this->rewrite($this->types->schema($type)->fields, $values, '', $found);
		}

		return $found;
	}

	/**
	 * Rewrites the paths that name a file with an id in each entry given
	 * (or every one the report lists). Returns the ids of the entries
	 * changed, and the ones that couldn't be with why.
	 *
	 * @param  ?list<string> $ids
	 * @return array{changed: list<string>, failed: array<string, string>}
	 * @throws MediaException
	 */
	public function write(?array $ids = null): array
	{
		$changed = [];
		$failed  = [];

		foreach ($ids ?? array_map('strval', array_keys($this->report())) as $id) {
			$found = $this->content->find($id);

			if ($found === null || ! $this->types->has($found->type->name)) {
				$failed[$id] = 'No entry has this id.';
				continue;
			}

			try {
				$entry = $this->content->editable($id);
			} catch (WriteException $e) {
				$failed[$id] = $e->getMessage();
				continue;
			}

			$values = [];
			$front  = $this->rewrite($this->types->schema($found->type->name)->fields, $entry->frontMatter, '', $values);
			$set    = [];

			foreach ($front as $key => $value) {
				if (is_string($key) && $value !== ($entry->frontMatter[$key] ?? null)) {
					$set[$key] = $value;
				}
			}

			if ($set === []) {
				continue;
			}

			try {
				$changed[] = $this->content->change($id, new EntryChanges($set))->id ?? $id;
			} catch (WriteException $e) {
				$failed[$id] = $e->getMessage();
			}
		}

		return ['changed' => $changed, 'failed' => $failed];
	}

	/**
	 * Rewrites a map's media values by its fields, noting each path found
	 * in `$values`, and returns the map.
	 *
	 * @param  array<array-key, Field>                                 $fields
	 * @param  array<array-key, mixed>                                 $data
	 * @param  list<array{field: string, path: string, id: ?string}> $values
	 * @return array<array-key, mixed>
	 * @throws MediaException
	 */
	private function rewrite(array $fields, array $data, string $at, array &$values): array
	{
		foreach ($fields as $field) {
			$key = array_find([$field->name, ...$field->aliases], static fn (string $name): bool => array_key_exists($name, $data));

			if ($key !== null && $field->name !== '') {
				$data[$key] = $this->value($field, $data[$key], $at === '' ? $field->name : "{$at}.{$field->name}", $values);
			}
		}

		return $data;
	}

	/**
	 * Rewrites one value by its field.
	 *
	 * @param  list<array{field: string, path: string, id: ?string}> $values
	 * @throws MediaException
	 */
	private function value(Field $field, mixed $value, string $at, array &$values): mixed
	{
		if ($field instanceof MediaField) {
			if (! is_string($value) || trim($value) === '' || MediaValues::isId($value)) {
				return $value;
			}

			$id       = $this->media->id($value);
			$values[] = ['field' => $at, 'path' => $value, 'id' => $id];

			return $id ?? $value;
		}

		if ($field instanceof ListField && is_array($value) && array_is_list($value)) {
			foreach ($value as $index => $item) {
				$value[$index] = $this->value($field->item, $item, "{$at}.{$index}", $values);
			}

			return $value;
		}

		if ($field instanceof ListField && is_string($value)) {
			return $this->value($field->item, $value, $at, $values);
		}

		if ($field instanceof ObjectField && is_array($value) && ! array_is_list($value)) {
			return $this->rewrite($field->schema->fields, $value, $at, $values);
		}

		return $value;
	}

	/**
	 * Returns an entry record's text field.
	 */
	private static function text(Record $record, string $key): string
	{
		$value = $record->fields[$key] ?? null;

		return is_string($value) ? $value : '';
	}
}
