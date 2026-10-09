<?php

/**
 * Index store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Index;

use Closure;
use DateTimeImmutable;
use Exception;
use Override;
use Blush\Container\Attributes\Defer;
use Blush\Content\EntryFields;
use Blush\Content\Record\EntryTable;
use Blush\Content\Status;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Tree;
use Blush\Content\Writer\ContentWriter;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\FilesystemWriter;
use Blush\Content\Writer\WriteConflict;
use Blush\Content\Writer\WriteException;
use Blush\Core\AppConfig;
use Blush\Storage\File\FileTransactions;
use Blush\Storage\Record\Aggregate;
use Blush\Storage\Record\ArrayEvaluator;
use Blush\Storage\Record\Condition;
use Blush\Storage\Record\ConditionGroup;
use Blush\Storage\Record\InvalidRecord;
use Blush\Storage\Record\Junction;
use Blush\Storage\Record\Operator;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordConflict;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordResult;
use Blush\Storage\Record\RecordStore;
use Blush\Storage\Record\RecordStoreFailure;
use Blush\Storage\Record\Ref;
use Blush\Storage\Record\Related;
use Blush\Storage\Record\Subquery;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageArea;
use Blush\Storage\StorageException;

/**
 * The filesystem driver's content tables (D-653): `entries` and `refs`
 * (D-649) as its index has them, Markdown files underneath. The file
 * record store hands it the content area's two tables.
 *
 * **Reading** runs record queries over the index's rows as they're
 * stored (`SnapshotRecords`), so nothing is built for an entry a query
 * doesn't find; a record found carries its Markdown, read from its file,
 * unless the query leaves content out. An entry's version is its file's
 * hash.
 *
 * **Writing an entry** compares the record with the stored one and does
 * what files need, through the content writer, so every file convention
 * holds (D-078): changed fields and columns become a front matter edit
 * that keeps comments and aliases, a new slug a rename, a new parent (in
 * a tree) a move, and `trash` and back a trash and a restore. A new
 * entry is written where its type keeps them, keeping its id. A type,
 * language, or original can't change on files. **Refs** are written in
 * their entries' front matter, so on files they're read-only here.
 *
 * Transactions take the filesystem driver's lock; the writer's own
 * writes aren't put back when one fails (a limit on files,
 * `open-questions.md`).
 *
 * @phpstan-import-type Row from ArrayEvaluator
 */
final readonly class IndexStore implements RecordStore
{
	/**
	 * Columns that are front matter keys as they are.
	 *
	 * @var list<string>
	 */
	private const array FRONT_MATTER = ['title', 'visibility', 'position'];

	/**
	 * The keys whose rows the index looks up for a query, by table, most
	 * narrowing first.
	 *
	 * @var array<string, list<string>>
	 */
	private const array LOOKUPS = [
		EntryTable::TABLE => ['id', 'parent_id', 'original_id'],
		Ref::TABLE        => ['source_id', 'target_id']
	];

	/**
	 * @param Closure(): ContentWriter    $writer
	 * @param Closure(): FilesystemWriter $files
	 */
	public function __construct(
		private IndexFreshness $index,
		private ContentTypes $types,
		private AppConfig $app,
		private FileTransactions $transactions,
		#[Defer(ContentWriter::class)] private Closure $writer,
		#[Defer(FilesystemWriter::class)] private Closure $files,
		private ArrayEvaluator $evaluator = new ArrayEvaluator()
	) {}

	/**
	 * Returns whether a table is one this store keeps.
	 */
	public static function keeps(Table $table): bool
	{
		return in_array($table->name, [EntryTable::TABLE, Ref::TABLE], true) && $table->area === EntryTable::table()->area;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function find(Table $table, string $id): ?Record
	{
		$row = $table->name === EntryTable::TABLE
			? $this->records()->entry($id)
			: array_find($this->records()->refs(), static fn (array $ref): bool => $ref['id'] === strtolower($id));

		return $row === null ? null : $this->record($table, $row, content: true);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function findByKey(Table $table, string $key): ?Record
	{
		throw new InvalidRecord(sprintf('"%s" has no key; find its records by id.', $table->name));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function findMany(Table $table, array $ids): array
	{
		$found = [];

		foreach ($ids as $id) {
			$record = $this->find($table, $id);

			if ($record !== null) {
				$found[$record->id] = $record;
			}
		}

		return $found;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function select(Table $table, RecordQuery $query): RecordResult
	{
		$found = $this->evaluator->select($table, $query, $this->rowsFor($table, $query), $this->refs(...));

		if (! $query->content || $table->name !== EntryTable::TABLE) {
			return $found;
		}

		return new RecordResult(
			array_map(fn (Record $record): Record => $record->withContent($this->content($record->id)), $found->records),
			$found->total,
			$found->refs
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function count(Table $table, RecordQuery $query): int
	{
		return $this->evaluator->count($table, $query, $this->rowsFor($table, $query), $this->refs(...));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function countBy(Table $table, RecordQuery $query, string $key): array
	{
		return $this->evaluator->countBy($table, $query, $key, $this->rowsFor($table, $query), $this->refs(...));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function aggregate(Table $table, RecordQuery $query, Aggregate $function, string $key): int|float|string|bool|null
	{
		return $this->evaluator->aggregate($table, $query, $function, $key, $this->rowsFor($table, $query), $this->refs(...));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function save(Table $table, Record $record, ?string $version = null): Record
	{
		$this->assertEntries($table, 'written');

		return $this->transaction(function () use ($table, $record, $version): Record {
			$stored = $this->records()->entry($record->id);

			RecordConflict::check($table, $record->id, $stored === null ? null : $this->record($table, $stored, content: false), $version);

			try {
				$stored === null ? $this->create($record) : $this->update($stored, $record);
			} catch (WriteConflict $error) {
				throw new RecordConflict($error->getMessage(), previous: $error);
			} catch (WriteException $error) {
				throw new RecordStoreFailure($error->getMessage(), previous: $error);
			}

			return $this->find($table, $record->id) ?? throw new RecordStoreFailure(sprintf('The entry %s was written, but the index doesn\'t have it.', $record->id));
		});
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function delete(Table $table, string $id, ?string $version = null): void
	{
		$this->assertEntries($table, 'deleted');

		$this->transaction(function () use ($table, $id, $version): void {
			$stored = $this->records()->entry($id);

			RecordConflict::check($table, $id, $stored === null ? null : $this->record($table, $stored, content: false), $version);

			if ($stored === null) {
				return;
			}

			try {
				($this->writer)()->delete($stored['id'], $stored['version'] ?? null);
			} catch (WriteConflict $error) {
				throw new RecordConflict($error->getMessage(), previous: $error);
			} catch (WriteException $error) {
				throw new RecordStoreFailure($error->getMessage(), previous: $error);
			}
		});
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function transaction(Closure $write): mixed
	{
		try {
			return $this->transactions->run($write);
		} catch (StorageException $error) {
			throw new RecordStoreFailure($error->getMessage(), previous: $error);
		}
	}

	/**
	 * A table's rows, for the evaluator.
	 *
	 * @return list<Row>
	 */
	private function rows(Table $table): array
	{
		return match ($table->name) {
			EntryTable::TABLE => $this->records()->entries,
			Ref::TABLE        => $this->records()->refs(),
			default           => []
		};
	}

	/**
	 * A table's rows for a query, for the evaluator: when every condition
	 * must hold, none is a subquery or a related one, and one asks for an
	 * id or a looked-up column to be (`=`) or be among (`in`) some values,
	 * only the rows with those values, found in the index's lookups
	 * (`SnapshotRecords::rowsWhere()`), which the evaluator still checks
	 * against every condition; else every row.
	 *
	 * @return Closure(Table): list<Row>
	 */
	private function rowsFor(Table $table, RecordQuery $query): Closure
	{
		$narrowed = $this->narrowed($table, $query->conditions);

		return fn (Table $of): array => $narrowed !== null && $of->name === $table->name && $of->area === $table->area ? $narrowed : $this->rows($of);
	}

	/**
	 * The rows a query's own condition narrows a table to, or `null`.
	 *
	 * @return ?list<Row>
	 */
	private function narrowed(Table $table, ConditionGroup $conditions): ?array
	{
		if ($conditions->junction !== Junction::All || self::nests($conditions)) {
			return null;
		}

		foreach (self::LOOKUPS[$table->name] ?? [] as $key) {
			foreach ($conditions->conditions as $condition) {
				if ($condition instanceof Condition && $condition->key === $key && ($condition->operator === Operator::Equal || $condition->operator === Operator::In)) {
					$values = $condition->operator === Operator::Equal ? [$condition->value] : $condition->value;

					return is_array($values) ? $this->records()->rowsWhere($table->name, $key, array_values($values)) : null;
				}
			}
		}

		return null;
	}

	/**
	 * Whether a group holds a subquery, a related condition, or a group,
	 * any of which may read the same table.
	 */
	private static function nests(ConditionGroup $conditions): bool
	{
		return array_any($conditions->conditions, static fn (Condition|ConditionGroup|Related $condition): bool => ! $condition instanceof Condition || $condition->value instanceof Subquery);
	}

	/**
	 * Refs looked up, for related conditions: the index's, built once a
	 * request.
	 *
	 * @return array<string, list<string>>
	 */
	private function refs(StorageArea $area, string $relation, bool $inverse): array
	{
		return $this->records()->refsBy($relation, $inverse);
	}

	/**
	 * The index's rows, brought up to date once a request.
	 */
	private function records(): SnapshotRecords
	{
		return $this->index->fresh()->records();
	}

	/**
	 * A row as a record, an entry's with its Markdown when asked.
	 *
	 * @param Row $row
	 */
	private function record(Table $table, array $row, bool $content): Record
	{
		$record = ArrayEvaluator::record($row, false);

		return $content && $table->name === EntryTable::TABLE ? $record->withContent($this->content($record->id)) : $record;
	}

	/**
	 * An entry's Markdown, from its file (an entry without an id's
	 * too, by the steady one its path gives it).
	 */
	private function content(string $id): ?string
	{
		$path = $this->records()->path($id);

		try {
			return $path === null ? null : ($this->files)()->load($path)->body;
		} catch (WriteException) {
			return null;
		}
	}

	/**
	 * Writes a new entry where its type keeps them, keeping its id: under
	 * its parent in a tree, as its type's landing page for no slug, else
	 * by its slug (and its type's file name and folder patterns).
	 *
	 * @throws InvalidRecord|WriteException
	 */
	private function create(Record $record): void
	{
		$fields = $record->fields;
		$type   = $this->types->find(is_string($fields['type'] ?? null) ? $fields['type'] : '') ?? throw new InvalidRecord(sprintf('The entry %s needs a "type" the site has.', $record->id));
		$slug   = is_string($fields['slug'] ?? null) ? $fields['slug'] : throw new InvalidRecord(sprintf('The entry %s needs a "slug".', $record->id));

		if (($fields['original_id'] ?? null) !== null || ! in_array($fields['language'] ?? null, [null, '', $this->app->languages->default->code], true)) {
			throw new InvalidRecord('On files, a translation is a file named for its language; it can\'t be written here yet.');
		}

		$front   = [];

		foreach (is_array($fields['fields'] ?? null) ? $fields['fields'] : [] as $key => $value) {
			$front[(string) $key] = $value;
		}

		$set     = [...$front, ...array_filter($this->frontMatter([], $fields), static fn (mixed $value): bool => $value !== null), EntryFields::ID => $record->id];
		$changes = new EntryChanges($set, [], $record->content ?? '');
		$parent  = is_string($fields['parent_id'] ?? null) ? $fields['parent_id'] : null;
		$writer  = ($this->writer)();

		match (true) {
			$parent !== null && $type instanceof Tree => $writer->create($type, $slug, $changes, $parent),
			$parent !== null                          => throw new InvalidRecord(sprintf('On files, only a tree\'s pages have a parent by place; %s name theirs in front matter.', $type->labels->plural)),
			$slug === ''                              => $writer->createAt($type, 'index', $changes),
			default                                   => $writer->create($type, $slug, $changes, null, self::date($fields['published'] ?? null))
		};
	}

	/**
	 * Writes what changed in an entry: front matter (and its Markdown),
	 * then its trash status, its slug, and its parent. The version was
	 * checked first, in the transaction, so only the first write checks
	 * it again.
	 *
	 * @param  Row $stored
	 * @throws InvalidRecord|WriteConflict|WriteException
	 */
	private function update(array $stored, Record $record): void
	{
		$old = $stored['fields'];
		$new = $record->fields;

		foreach (['type', 'language', 'original_id'] as $key) {
			if (($new[$key] ?? null) !== ($old[$key] ?? null)) {
				throw new InvalidRecord(sprintf('On files, an entry\'s %s can\'t change; it\'s where the file is.', str_replace('_id', '', $key)));
			}
		}

		$writer  = ($this->writer)();
		$id      = $stored['id'];
		$version = $stored['version'] ?? null;
		$changes = $this->changes($old, $new, $record->content === null ? null : [$record->content, $this->content($id)]);

		if ($changes !== null) {
			$id      = $writer->update($id, $changes, $version);
			$version = null;
		}

		$was = $old['status'] ?? null;
		$is  = $new['status'] ?? null;

		if ($is !== $was && ($is === Status::Trash->value || $was === Status::Trash->value)) {
			$id = $is === Status::Trash->value
				? $writer->trash($id, $version)
				: $writer->restore($id, $version, Status::tryFrom(is_string($is) ? $is : '') ?? Status::Draft);
			$version = null;
		}

		if (($new['slug'] ?? null) !== ($old['slug'] ?? null)) {
			if (! is_string($new['slug'] ?? null) || $new['slug'] === '' || $old['slug'] === '') {
				throw new InvalidRecord('On files, a landing page\'s slug doesn\'t change, and an entry keeps a slug.');
			}

			$id      = $writer->rename($id, $new['slug'], $version);
			$version = null;
		}

		if (($new['parent_id'] ?? null) !== ($old['parent_id'] ?? null)) {
			$type = $this->types->find(is_string($old['type'] ?? null) ? $old['type'] : '');

			if (! $type instanceof Tree) {
				throw new InvalidRecord('On files, only a tree\'s pages move to another parent here; others name theirs in front matter.');
			}

			$writer->move($id, is_string($new['parent_id'] ?? null) ? $new['parent_id'] : null, $version);
		}
	}

	/**
	 * The front matter edit an update makes: each changed column a front
	 * matter key, each changed field set or removed, and the Markdown when
	 * it differs; `null` for none. Status goes through trash and restore
	 * when it's trash on either side.
	 *
	 * @param  array<string, mixed>     $old
	 * @param  array<string, mixed>     $new
	 * @param  ?array{string, ?string}  $content The new Markdown and the file's.
	 */
	private function changes(array $old, array $new, ?array $content): ?EntryChanges
	{
		$oldFront = is_array($old['fields'] ?? null) ? $old['fields'] : [];
		$newFront = is_array($new['fields'] ?? null) ? $new['fields'] : [];
		$set      = [];
		$remove   = [];

		foreach ($newFront as $key => $value) {
			if (! array_key_exists($key, $oldFront) || $oldFront[$key] !== $value) {
				$set[(string) $key] = $value;
			}
		}

		foreach (array_keys($oldFront) as $key) {
			if (! array_key_exists($key, $newFront)) {
				$remove[] = (string) $key;
			}
		}

		$set = [...$set, ...$this->frontMatter($old, $new)];

		foreach (array_keys($set) as $key) {
			if ($set[$key] === null) {
				unset($set[$key]);
				$remove[] = $key;
			}
		}

		unset($set[EntryFields::ID]);

		$remove = array_values(array_diff(array_unique($remove), [EntryFields::ID, ...array_keys($set)]));
		$body   = $content !== null && $content[0] !== $content[1] ? $content[0] : null;

		return $set === [] && $remove === [] && $body === null ? null : new EntryChanges($set, $remove, $body);
	}

	/**
	 * The columns that changed, as front matter: `title`, `visibility`,
	 * `position` as they are, `published` and `updated` in the site's
	 * time zone, and a status other than trash; `null` removes one.
	 *
	 * @param  array<string, mixed> $old
	 * @param  array<string, mixed> $new
	 * @return array<string, mixed>
	 */
	private function frontMatter(array $old, array $new): array
	{
		$set = [];

		foreach (self::FRONT_MATTER as $key) {
			if (array_key_exists($key, $new) && ($new[$key] ?? null) !== ($old[$key] ?? null)) {
				$set[$key] = $new[$key];
			}
		}

		foreach (['published', 'updated'] as $key) {
			if (array_key_exists($key, $new) && ($new[$key] ?? null) !== ($old[$key] ?? null)) {
				$set[$key] = self::date($new[$key])?->setTimezone($this->app->timezone())->format('Y-m-d H:i:s P');
			}
		}

		$status = $new['status'] ?? null;

		if (array_key_exists('status', $new) && $status !== ($old['status'] ?? null) && $status !== Status::Trash->value && ($old['status'] ?? null) !== Status::Trash->value) {
			$set['status'] = $status;
		}

		return $set;
	}

	/**
	 * A time as the table keeps it, as a date, or `null`.
	 */
	private static function date(mixed $time): ?DateTimeImmutable
	{
		if (! is_string($time) || $time === '') {
			return null;
		}

		try {
			return new DateTimeImmutable($time);
		} catch (Exception) {
			return null;
		}
	}

	/**
	 * Refuses writes to refs, which files keep in front matter.
	 *
	 * @throws InvalidRecord
	 */
	private function assertEntries(Table $table, string $what): void
	{
		if ($table->name !== EntryTable::TABLE) {
			throw new InvalidRecord(sprintf('On files, refs are %s through their entries\' front matter (the relation\'s field), not here.', $what));
		}
	}
}
