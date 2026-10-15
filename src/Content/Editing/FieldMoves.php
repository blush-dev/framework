<?php

/**
 * Field moves.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Editing;

use Blush\Activity\Actor;
use Blush\Content\Entries;
use Blush\Content\Record\EntryTable;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\WriteException;
use Blush\Field\Field;
use Blush\Field\Fields\ObjectField;
use Blush\Storage\Record\RecordException;
use Blush\Storage\Record\RecordStores;
use Blush\Workflow\Status;

/**
 * Moves the values of fields a set has put somewhere new (D-780): into a
 * group, out of one, or from one group to another. A group keeps its
 * fields' values under its key (`details.servings`), so a field that
 * moves leaves its values behind unless they move too.
 *
 * `between()` finds the moves from a set's fields before and after, by
 * key: a field whose key is at one place before and another after. A key
 * used more than once, before or after, isn't followed. A group that
 * moves takes its fields with it, so they aren't moves of their own.
 *
 * `apply()` moves the values in every entry of the types given that has
 * one at the old place, through `EntryEditor`, so each change is in the
 * activity log with Undo; an entry in the trash is changed directly,
 * since the editor doesn't edit it. An entry with a value at the new
 * place already keeps both, and is listed as kept.
 */
final readonly class FieldMoves
{
	public function __construct(
		private RecordStores $stores,
		private Entries $content,
		private EntryEditor $editor
	) {}

	/**
	 * Returns the moves between a set's fields before and after, each the
	 * keys `from` and `to` (a group's key, then the field's).
	 *
	 * @param  array<array-key, Field> $before
	 * @param  array<array-key, Field> $after
	 * @return list<array{from: list<string>, to: list<string>}>
	 */
	public static function between(array $before, array $after): array
	{
		$old   = self::places($before);
		$new   = self::places($after);
		$moves = [];

		foreach ($old as $name => $from) {
			$to = $new[$name] ?? null;

			if ($from !== null && $to !== null && $from !== $to) {
				$moves[] = ['from' => $from, 'to' => $to];
			}
		}

		// A field whose group moved went with it.
		return array_values(array_filter($moves, static fn (array $move): bool => ! array_any($moves, static fn (array $other): bool => $other !== $move
			&& self::under($move['from'], $other['from'])
			&& self::under($move['to'], $other['to'])
			&& array_slice($move['from'], count($other['from'])) === array_slice($move['to'], count($other['to'])))));
	}

	/**
	 * Moves the values in the entries of the types given. Returns the ids
	 * of the entries `moved`, those that `kept` a value already at a new
	 * place, and those that `failed`, with why.
	 *
	 * @param  list<string>                                         $types
	 * @param  list<array{from: list<string>, to: list<string>}>    $moves
	 * @return array{moved: list<string>, kept: list<string>, failed: array<string, string>}
	 */
	public function apply(Actor $actor, array $types, array $moves): array
	{
		$done = ['moved' => [], 'kept' => [], 'failed' => []];

		if ($moves === [] || $types === []) {
			return $done;
		}

		try {
			$records = $this->stores->query(EntryTable::table())->withoutContent()->get();
		} catch (RecordException $e) {
			$done['failed']['*'] = $e->getMessage();

			return $done;
		}

		foreach ($records as $record) {
			$front = $record->fields['fields'] ?? null;

			if (! in_array($record->fields['type'] ?? null, $types, true) || ! is_array($front)) {
				continue;
			}

			if (! array_any($moves, static fn (array $move): bool => self::filled(self::at($front, $move['from'])))) {
				continue;
			}

			$this->move($actor, (string) $record->id, $moves, $done);
		}

		return $done;
	}

	/**
	 * Moves one entry's values.
	 *
	 * @param list<array{from: list<string>, to: list<string>}>                                $moves
	 * @param array{moved: list<string>, kept: list<string>, failed: array<string, string>} $done
	 */
	private function move(Actor $actor, string $id, array $moves, array &$done): void
	{
		$entry = $this->content->find($id);

		if ($entry === null) {
			return;
		}

		try {
			$file = $this->content->editable($entry);
		} catch (WriteException $e) {
			$done['failed'][$id] = $e->getMessage();

			return;
		}

		$front = $file->frontMatter;
		$kept  = false;

		foreach ($moves as $move) {
			$value = self::at($front, $move['from']);

			if (! self::filled($value)) {
				continue;
			}

			if (self::filled(self::at($front, $move['to']))) {
				$kept = true;
				continue;
			}

			$front = self::without(self::with($front, $move['to'], $value), $move['from']);
		}

		$set    = [];
		$remove = [];

		foreach ($front as $key => $value) {
			if (is_string($key) && $value !== ($file->frontMatter[$key] ?? null)) {
				$set[$key] = $value;
			}
		}

		foreach (array_keys($file->frontMatter) as $key) {
			if (is_string($key) && ! array_key_exists($key, $front)) {
				$remove[] = $key;
			}
		}

		if ($kept) {
			$done['kept'][] = $id;
		}

		if ($set === [] && $remove === []) {
			return;
		}

		try {
			if ($entry->status->is(Status::TRASH)) {
				$this->content->change($entry, new EntryChanges($set, $remove), $file->version);
			} else {
				$this->editor->update($actor, $entry, new EntryEdit($set, $remove), $file->version);
			}

			$done['moved'][] = $id;
		} catch (EditRefused | WriteException $e) {
			$done['failed'][$id] = $e->getMessage();
		}
	}

	/**
	 * Where each key is among fields, through their groups, or `null` for
	 * a key that's in more than one place.
	 *
	 * @param  array<array-key, Field>             $fields
	 * @param  list<string>                        $at
	 * @return array<string, list<string>|null>
	 */
	private static function places(array $fields, array $at = []): array
	{
		$places = [];

		foreach ($fields as $field) {
			if ($field->name === '') {
				continue;
			}

			$path = [...$at, $field->name];
			$more = [$field->name => $path];

			if ($field instanceof ObjectField) {
				$more += self::places($field->schema->fields, $path);
			}

			foreach ($more as $name => $place) {
				$places[$name] = array_key_exists($name, $places) ? null : $place;
			}
		}

		return $places;
	}

	/**
	 * Whether a path is inside another.
	 *
	 * @param list<string> $path
	 * @param list<string> $parent
	 */
	private static function under(array $path, array $parent): bool
	{
		return count($path) > count($parent) && array_slice($path, 0, count($parent)) === $parent;
	}

	/**
	 * The value at a path, or `null`.
	 *
	 * @param array<array-key, mixed> $data
	 * @param list<string>            $path
	 */
	private static function at(array $data, array $path): mixed
	{
		$value = $data;

		foreach ($path as $key) {
			if (! is_array($value) || array_is_list($value) && $value !== [] || ! array_key_exists($key, $value)) {
				return null;
			}

			$value = $value[$key];
		}

		return $value;
	}

	/**
	 * A map with a value at a path, making the groups on the way.
	 *
	 * @param  array<array-key, mixed> $data
	 * @param  list<string>            $path
	 * @return array<array-key, mixed>
	 */
	private static function with(array $data, array $path, mixed $value): array
	{
		$key = $path[0];

		if (count($path) === 1) {
			$data[$key] = $value;

			return $data;
		}

		$inner      = $data[$key] ?? [];
		$data[$key] = self::with(is_array($inner) ? $inner : [], array_slice($path, 1), $value);

		return $data;
	}

	/**
	 * A map without the value at a path, and without the groups that
	 * leaves empty.
	 *
	 * @param  array<array-key, mixed> $data
	 * @param  list<string>            $path
	 * @return array<array-key, mixed>
	 */
	private static function without(array $data, array $path): array
	{
		$key = $path[0];

		if (count($path) === 1) {
			unset($data[$key]);

			return $data;
		}

		if (! is_array($data[$key] ?? null)) {
			return $data;
		}

		$inner = self::without($data[$key], array_slice($path, 1));

		if ($inner === []) {
			unset($data[$key]);
		} else {
			$data[$key] = $inner;
		}

		return $data;
	}

	/**
	 * Whether a value is something: not missing, empty text, or an empty
	 * list or map.
	 */
	private static function filled(mixed $value): bool
	{
		return ! in_array($value, [null, '', []], true);
	}
}
