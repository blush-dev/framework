<?php

/**
 * Relation changes.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

use Blush\Content\Entries;
use Blush\Content\Record\EntryTable;
use Blush\Content\Status;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\WriteException;
use Blush\Storage\Record\Operator;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Ref;

/**
 * Changing a relation that entries use (D-600), for the admin's form and
 * `content:relation`:
 *
 * - `check()` says what a new definition does to them: refused when it
 *   points at another type while entries have values, or takes one
 *   where an entry has several; the entries a new key would move; the
 *   entries of types no longer filed; and warnings for tighter limits.
 * - `apply()` makes it: a new key keeps the old one as an alias unless
 *   the files are rewritten, and an unfiled type's values are removed
 *   when asked.
 * - `strip()` removes a relation's values, with their ids, from entries.
 *
 * Values are read from the `entries` records (D-649), as written in
 * their fields, and entries are changed by id through `Entries` (D-654):
 * an entry kept without an id is named by its record's steady one, and
 * given one of its own when it's changed.
 */
final readonly class RelationChanges
{
	public function __construct(
		private RecordStores $stores,
		private Entries $content
	) {}

	/**
	 * Returns the entries with a value in a relation (by id, with how
	 * many values), of its `from` types or only the types given.
	 *
	 * @param  ?list<string> $types
	 * @return array<string, int>
	 */
	public function uses(Relation $relation, ?array $types = null): array
	{
		$uses = [];

		foreach ($this->records() as $record) {
			$type = self::type($record);

			if (! $relation->isFrom($type) || ($types !== null && ! in_array($type, $types, true))) {
				continue;
			}

			$count = count(LinkResolver::values($relation->valueIn(self::front($record))));

			if ($count > 0) {
				$uses[$record->id] = $count;
			}
		}

		return $uses;
	}

	/**
	 * Returns how many entries have a value in each relation given, in one
	 * pass over the index (D-610), by relation name.
	 *
	 * @param  array<array-key, Relation> $relations
	 * @return array<string, int>
	 */
	public function counts(array $relations): array
	{
		$counts = array_fill_keys(array_map(static fn (Relation $relation): string => $relation->name, array_values($relations)), 0);

		foreach ($this->records() as $record) {
			$values = self::front($record);

			foreach ($relations as $relation) {
				if ($relation->isFrom(self::type($record)) && LinkResolver::values($relation->valueIn($values)) !== []) {
					$counts[$relation->name]++;
				}
			}
		}

		return $counts;
	}

	/**
	 * Returns the slugs of a type's entries with more values in a relation
	 * than a number (D-610), for the list of the entries a change is
	 * refused or warned over.
	 *
	 * @return list<string>
	 */
	public function slugsOver(Relation $relation, string $type, int $above): array
	{
		$records = $this->records();
		$slugs   = [];

		foreach ($this->uses($relation, [$type]) as $id => $count) {
			$slug = $records[$id]->fields['slug'] ?? null;

			if ($count > $above && is_string($slug)) {
				$slugs[] = $slug;
			}
		}

		return array_values(array_unique($slugs));
	}

	/**
	 * Says what replacing a relation's definition does to the entries
	 * using it.
	 */
	public function check(Relation $old, Relation $new): RelationCheck
	{
		$uses     = $this->uses($old);
		$refusal  = null;
		$refused  = null;
		$inWay    = [];
		$warnings = [];

		if ($old->to !== $new->to && $uses !== []) {
			$refused = 'type';
			$inWay   = $uses;
			$refusal = sprintf('%s a value in "%s", so it can\'t point at another type; make a new relation instead.', self::entries(count($uses), 'has', 'have'), $old->name);
		} elseif ($old->multiple && ! $new->multiple) {
			$several = array_filter($uses, static fn (int $count): bool => $count > 1);

			if ($several !== []) {
				$refused = 'one';
				$inWay   = $several;
				$refusal = sprintf('%s more than one value in "%s", so it can\'t take just one: %s.', self::entries(count($several), 'has', 'have'), $old->name, implode(', ', array_slice($this->titles(array_keys($several)), 0, 5)) . (count($several) > 5 ? ', …' : ''));
			}
		}

		$records = $this->records();
		$unfiled = $new->from === [] ? [] : array_values(array_unique(array_filter(
			array_map(static fn (string $id): string => isset($records[$id]) ? self::type($records[$id]) : '', array_map(strval(...), array_keys($uses))),
			static fn (string $type): bool => $type !== '' && ! in_array($type, $new->from, true)
		)));

		$live  = $this->live($uses);
		$fewer = 0;
		$over  = [];

		if ($new->min > $old->min) {
			$fewer  = count(array_filter($live, static fn (int $count): bool => $count < $new->min));
			$fewer += $new->min > 0 ? $this->liveWithout($new) : 0;

			if ($fewer > 0) {
				$warnings[] = sprintf('%s fewer than %d, so %s published again until %s more.', self::entries($fewer, 'has', 'have', 'published'), $new->min, $fewer === 1 ? 'it can\'t be' : 'they can\'t be', $fewer === 1 ? 'it has' : 'they have');
			}
		}

		if ($new->max !== null && ($old->max === null || $new->max < $old->max)) {
			$over = array_filter($uses, static fn (int $count): bool => $count > $new->max);
			$many = count($over);

			if ($many > 0) {
				$warnings[] = sprintf('%s more than %d; lint reports %s, and %s publish again until %s fewer.', self::entries($many, 'has', 'have'), $new->max, $many === 1 ? 'it' : 'them', $many === 1 ? 'it won\'t' : 'they won\'t', $many === 1 ? 'it has' : 'they have');
			}
		}

		$oldMax = $old->inverse === false ? null : $old->inverse->max;
		$newMax = $new->inverse === false ? null : $new->inverse->max;
		$beyond = 0;

		if ($newMax !== null && ($oldMax === null || $newMax < $oldMax)) {
			$beyond = $this->overInverse($old->name, $newMax);

			if ($beyond > 0) {
				$warnings[] = sprintf('%s named by more than %d %s; lint reports them.', $beyond === 1 ? '1 entry is' : "{$beyond} entries are", $newMax, $newMax === 1 ? 'entry' : 'entries');
			}
		}

		$moved = $old->field === $new->field ? 0 : count($uses);

		return new RelationCheck(
			refusal: $refusal,
			warnings: $warnings,
			uses: count($uses),
			moved: $moved,
			unfiled: $unfiled,
			stripped: $unfiled === [] ? 0 : count($this->uses($old, $unfiled)),
			refused: $refused,
			inWay: $this->items($inWay),
			inWayCount: count($inWay),
			over: $this->items($over),
			overCount: count($over),
			fewer: $fewer,
			inverseOver: $beyond
		);
	}

	/**
	 * Names the entries with the most values, up to `RelationCheck::LISTED`,
	 * then by title.
	 *
	 * @param  array<array-key, int> $uses Values by id.
	 * @return list<array{id: ?string, type: string, title: string, count: int}>
	 */
	private function items(array $uses): array
	{
		$records = $this->records();
		$items   = [];

		foreach ($uses as $id => $count) {
			$record = $records[(string) $id] ?? null;

			if ($record !== null) {
				$items[] = ['id' => $record->id, 'type' => self::type($record), 'title' => self::title($record), 'count' => $count];
			}
		}

		usort($items, static fn (array $a, array $b): int => [$b['count'], $a['title']] <=> [$a['count'], $b['title']]);

		// An entry kept without an id has none to link to.
		return array_map(fn (array $item): array => [...$item, 'id' => $this->content->find($item['id'])?->id], array_slice($items, 0, RelationCheck::LISTED));
	}

	/**
	 * Makes a new definition fit the files: refused as `check()` refuses;
	 * a new key moves the values to it when `$rewrite`, else keeps the old
	 * keys as aliases; and an unfiled type's values are removed when
	 * `$strip`. Returns the definition to save.
	 *
	 * @throws InvalidRelation When it's refused.
	 * @throws WriteException  When a file can't be changed.
	 */
	public function apply(Relation $old, Relation $new, bool $rewrite = false, bool $strip = false): Relation
	{
		$check = $this->check($old, $new);

		if ($check->refusal !== null) {
			throw new InvalidRelation($check->refusal);
		}

		if ($strip && $check->unfiled !== []) {
			$this->strip($old, $check->unfiled);
		}

		if ($old->field === $new->field) {
			return $new;
		}

		if ($rewrite) {
			$this->moveKey($old, $new->field);

			return $new;
		}

		$aliases = array_values(array_unique(array_diff([...$new->aliases, ...$old->keys()], [$new->field])));

		return Relation::fromArray([...$new->toArray(), 'aliases' => $aliases]);
	}

	/**
	 * Removes a relation's values and their ids from the entries that
	 * have them (of its `from` types, or only those given). Returns the
	 * ids of the entries changed.
	 *
	 * @param  ?list<string> $types
	 * @return list<string>
	 * @throws WriteException When an entry can't be changed.
	 */
	public function strip(Relation $relation, ?array $types = null): array
	{
		$changed = [];

		foreach (array_keys($this->uses($relation, $types)) as $id) {
			$front  = $this->content->editable((string) $id)->frontMatter;
			$remove = array_values(array_filter($relation->keys(), static fn (string $key): bool => array_key_exists($key, $front)));
			$refs   = Refs::fromValue($front[Refs::FIELD] ?? null)->with($relation->name, []);
			$set    = [];

			if ($refs->isEmpty()) {
				$remove[] = Refs::FIELD;
			} else {
				$set[Refs::FIELD] = $refs->toArray();
			}

			$changed[] = $this->content->change((string) $id, new EntryChanges($set, array_values(array_intersect($remove, array_keys($front)))))->id ?? (string) $id;
		}

		return $changed;
	}

	/**
	 * Moves each entry's values in a relation from its key (or an alias)
	 * to a new one. Returns the ids of the entries changed.
	 *
	 * @return list<string>
	 * @throws WriteException When an entry can't be changed.
	 */
	public function moveKey(Relation $relation, string $field): array
	{
		$changed = [];

		foreach (array_keys($this->uses($relation)) as $id) {
			$front = $this->content->editable((string) $id)->frontMatter;
			$value = $relation->valueIn($front);

			if (! is_scalar($value) && ! is_array($value)) {
				continue;
			}

			$changed[] = $this->content->change((string) $id, new EntryChanges(
				[$field => $value],
				array_values(array_filter($relation->keys(), static fn (string $key): bool => $key !== $field && array_key_exists($key, $front)))
			))->id ?? (string) $id;
		}

		return $changed;
	}

	/**
	 * Returns the uses of live entries.
	 *
	 * @param  array<string, int> $uses
	 * @return array<string, int>
	 */
	private function live(array $uses): array
	{
		$records = $this->records();

		return array_filter($uses, static fn (string $id): bool => ($records[$id]->fields['status'] ?? null) === Status::Published->value, ARRAY_FILTER_USE_KEY);
	}

	/**
	 * Returns how many live entries of a relation's types have no value in
	 * it.
	 */
	private function liveWithout(Relation $relation): int
	{
		$uses  = $this->uses($relation);
		$count = 0;

		foreach ($this->records() as $id => $record) {
			if ($relation->isFrom(self::type($record)) && ($record->fields['status'] ?? null) === Status::Published->value && ! isset($uses[$id])) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Returns how many targets more entries name in a relation than a
	 * limit, not counting the trash (D-598).
	 */
	private function overInverse(string $relation, int $max): int
	{
		$table   = Ref::table(EntryTable::table()->area);
		$records = $this->records();
		$counts  = [];

		foreach ($this->stores->query($table)->where('relation', Operator::Equal, $relation)->get() as $ref) {
			$source = $ref->fields['source_id'] ?? null;
			$target = $ref->fields['target_id'] ?? null;

			if (is_string($source) && is_string($target) && isset($records[$source]) && ($records[$source]->fields['status'] ?? null) !== Status::Trash->value) {
				$counts[$target] = ($counts[$target] ?? 0) + 1;
			}
		}

		return count(array_filter($counts, static fn (int $count): bool => $count > $max));
	}

	/**
	 * Returns every entry's record, without its content, by id.
	 *
	 * @return array<string, Record>
	 */
	private function records(): array
	{
		$records = [];

		foreach ($this->stores->query(EntryTable::table())->withoutContent()->get() as $record) {
			$records[$record->id] = $record;
		}

		return $records;
	}

	/**
	 * Returns the titles of entries, by id, for naming them.
	 *
	 * @param  list<array-key> $ids
	 * @return list<string>
	 */
	private function titles(array $ids): array
	{
		$records = $this->records();

		return array_values(array_filter(array_map(static fn (int|string $id): ?string => isset($records[(string) $id]) ? self::title($records[(string) $id]) : null, $ids), is_string(...)));
	}

	/**
	 * Returns an entry record's type.
	 */
	private static function type(Record $record): string
	{
		$type = $record->fields['type'] ?? null;

		return is_string($type) ? $type : '';
	}

	/**
	 * Returns an entry record's title, else its slug.
	 */
	private static function title(Record $record): string
	{
		$title = $record->fields['title'] ?? null;
		$slug  = $record->fields['slug'] ?? null;

		return is_string($title) && $title !== '' ? $title : (is_string($slug) ? $slug : $record->id);
	}

	/**
	 * Returns an entry record's front matter.
	 *
	 * @return array<array-key, mixed>
	 */
	private static function front(Record $record): array
	{
		$front = $record->fields['fields'] ?? null;

		return is_array($front) ? $front : [];
	}

	/**
	 * Returns a count of entries with a verb.
	 */
	private static function entries(int $count, string $one, string $many, string $kind = ''): string
	{
		$noun = $kind === '' ? ($count === 1 ? 'entry' : 'entries') : ($count === 1 ? "{$kind} entry" : "{$kind} entries");

		return sprintf('%d %s %s', $count, $noun, $count === 1 ? $one : $many);
	}
}
