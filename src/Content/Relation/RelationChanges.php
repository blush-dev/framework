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

use Blush\Content\Index\ContentIndex;
use Blush\Content\Index\Indexer;
use Blush\Content\Status;
use Blush\Content\Writer\ContentWriter;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\WriteException;

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
 * - `strip()` removes a relation's values, with their ids, from files.
 *
 * Values are read from the index, brought up to date first.
 */
final readonly class RelationChanges
{
	public function __construct(
		private ContentIndex $index,
		private Indexer $indexer,
		private ContentWriter $writer
	) {}

	/**
	 * Returns the entries with a value in a relation (by path, with how
	 * many values), of its `from` types or only the types given.
	 *
	 * @param  ?list<string> $types
	 * @return array<string, int>
	 */
	public function uses(Relation $relation, ?array $types = null): array
	{
		$this->indexer->index();

		$uses = [];

		foreach ($this->index->snapshot()->records as $path => $record) {
			if (! $relation->isFrom($record['type']) || ($types !== null && ! in_array($record['type'], $types, true))) {
				continue;
			}

			$count = count(LinkResolver::values($relation->valueIn([...$record['extra'], ...$record['values']])));

			if ($count > 0) {
				$uses[(string) $path] = $count;
			}
		}

		return $uses;
	}

	/**
	 * Says what replacing a relation's definition does to the entries
	 * using it.
	 */
	public function check(Relation $old, Relation $new): RelationCheck
	{
		$uses     = $this->uses($old);
		$refusal  = null;
		$warnings = [];

		if ($old->to !== $new->to && $uses !== []) {
			$refusal = sprintf('%s a value in "%s", so it can\'t point at another type; make a new relation instead.', self::entries(count($uses), 'has', 'have'), $old->name);
		} elseif ($old->multiple && ! $new->multiple) {
			$several = array_keys(array_filter($uses, static fn (int $count): bool => $count > 1));

			if ($several !== []) {
				$refusal = sprintf('%s more than one value in "%s", so it can\'t take just one: %s.', self::entries(count($several), 'has', 'have'), $old->name, implode(', ', array_slice($several, 0, 5)) . (count($several) > 5 ? ', …' : ''));
			}
		}

		$unfiled = $new->from === [] ? [] : array_values(array_unique(array_filter(
			array_map(fn (string $path): string => $this->index->snapshot()->records[$path]['type'] ?? '', array_keys($uses)),
			static fn (string $type): bool => $type !== '' && ! in_array($type, $new->from, true)
		)));

		$live = $this->live($uses);

		if ($new->min > $old->min) {
			$few = count(array_filter($live, static fn (int $count): bool => $count < $new->min));
			$few += $new->min > 0 ? $this->liveWithout($new) : 0;

			if ($few > 0) {
				$warnings[] = sprintf('%s fewer than %d, so %s published again until %s more.', self::entries($few, 'has', 'have', 'published'), $new->min, $few === 1 ? 'it can\'t be' : 'they can\'t be', $few === 1 ? 'it has' : 'they have');
			}
		}

		if ($new->max !== null && ($old->max === null || $new->max < $old->max)) {
			$many = count(array_filter($uses, static fn (int $count): bool => $count > $new->max));

			if ($many > 0) {
				$warnings[] = sprintf('%s more than %d; lint reports %s, and %s publish again until %s fewer.', self::entries($many, 'has', 'have'), $new->max, $many === 1 ? 'it' : 'them', $many === 1 ? 'it won\'t' : 'they won\'t', $many === 1 ? 'it has' : 'they have');
			}
		}

		$oldMax = $old->inverse === false ? null : $old->inverse->max;
		$newMax = $new->inverse === false ? null : $new->inverse->max;

		if ($newMax !== null && ($oldMax === null || $newMax < $oldMax)) {
			$over = $this->overInverse($old->name, $newMax);

			if ($over > 0) {
				$warnings[] = sprintf('%s named by more than %d %s; lint reports them.', $over === 1 ? '1 entry is' : "{$over} entries are", $newMax, $newMax === 1 ? 'entry' : 'entries');
			}
		}

		$moved = $old->field === $new->field ? 0 : count($uses);

		return new RelationCheck($refusal, $warnings, count($uses), $moved, $unfiled, $unfiled === [] ? 0 : count($this->uses($old, $unfiled)));
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
	 * Removes a relation's values and their ids from the files that have
	 * them (of its `from` types, or only those given). Returns the paths
	 * changed.
	 *
	 * @param  ?list<string> $types
	 * @return list<string>
	 * @throws WriteException When a file can't be changed.
	 */
	public function strip(Relation $relation, ?array $types = null): array
	{
		$changed = [];

		foreach (array_keys($this->uses($relation, $types)) as $path) {
			$front  = $this->writer->load($path)->frontMatter;
			$remove = array_values(array_filter($relation->keys(), static fn (string $key): bool => array_key_exists($key, $front)));
			$refs   = Refs::fromValue($front[Refs::FIELD] ?? null)->with($relation->name, []);
			$set    = [];

			if ($refs->isEmpty()) {
				$remove[] = Refs::FIELD;
			} else {
				$set[Refs::FIELD] = $refs->toArray();
			}

			$this->writer->update($path, new EntryChanges($set, array_values(array_intersect($remove, array_keys($front)))));
			$changed[] = $path;
		}

		return $changed;
	}

	/**
	 * Moves each entry's values in a relation from its key (or an alias)
	 * to a new one. Returns the paths changed.
	 *
	 * @return list<string>
	 * @throws WriteException When a file can't be changed.
	 */
	public function moveKey(Relation $relation, string $field): array
	{
		$changed = [];

		foreach (array_keys($this->uses($relation)) as $path) {
			$front = $this->writer->load($path)->frontMatter;
			$value = $relation->valueIn($front);

			if (! is_scalar($value) && ! is_array($value)) {
				continue;
			}

			$this->writer->update($path, new EntryChanges(
				[$field => $value],
				array_values(array_filter($relation->keys(), static fn (string $key): bool => $key !== $field && array_key_exists($key, $front)))
			));
			$changed[] = $path;
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
		$records = $this->index->snapshot()->records;

		return array_filter($uses, static fn (string $path): bool => ($records[$path]['status'] ?? null) === Status::Published->value, ARRAY_FILTER_USE_KEY);
	}

	/**
	 * Returns how many live entries of a relation's types have no value in
	 * it.
	 */
	private function liveWithout(Relation $relation): int
	{
		$uses  = $this->uses($relation);
		$count = 0;

		foreach ($this->index->snapshot()->records as $path => $record) {
			if ($relation->isFrom($record['type']) && $record['status'] === Status::Published->value && ! isset($uses[(string) $path])) {
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
		$snapshot = $this->index->snapshot();
		$counts   = [];

		foreach ($snapshot->graph()->all() as $link) {
			$path = $snapshot->path($link->source);

			if ($link->relation === $relation && $path !== null && ($snapshot->records[$path]['status'] ?? null) !== Status::Trash->value) {
				$counts[$link->target] = ($counts[$link->target] ?? 0) + 1;
			}
		}

		return count(array_filter($counts, static fn (int $count): bool => $count > $max));
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
