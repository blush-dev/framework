<?php

/**
 * Record content writer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Writer;

use Closure;
use DateTimeInterface;
use Override;
use Psr\Clock\ClockInterface;
use Blush\Cache\ContentVersion;
use Blush\Content\EntryFields;
use Blush\Content\Record\EntryLocations;
use Blush\Content\Record\EntryRecords;
use Blush\Content\Record\EntryTable;
use Blush\Content\Relation\EntryTargets;
use Blush\Content\Relation\Link;
use Blush\Content\Relation\LinkResolver;
use Blush\Content\Relation\Refs;
use Blush\Content\Relation\Relation;
use Blush\Content\Relation\RelationKind;
use Blush\Content\Relation\Relations;
use Blush\Content\Status;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Visibility;
use Blush\Core\AppConfig;
use Blush\Field\FieldContext;
use Blush\Storage\Record\Operator;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordConflict;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordStore;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Ref;
use Blush\Storage\Record\Table;
use Blush\Support\Slug;
use Blush\Support\Uuid;

/**
 * Writes entries as records (D-662), for every driver that keeps content
 * in a `RecordStore` (a database): an entry and its `refs` rows are saved
 * in one transaction, with what a file's path means kept as columns.
 *
 * - **Front matter** is kept twice in the record (D-662): as written,
 *   under `written`, which `load()` gives the editor and changes apply
 *   to (a field's alias already used is kept); and normalized by the
 *   type's schema, with the columns worked out from it, as every
 *   driver's records have it (`EntryTable::fields()`).
 * - **Relations** are kept as `refs` rows only, never as written values:
 *   each is resolved when the entry is written (`LinkResolver`), and
 *   `load()` gives each relation's targets by their slugs now, so a
 *   renamed target is never stale. A value that names no entry is
 *   refused.
 * - **Places** are columns: a tree's page has its parent's id, a
 *   hierarchical collection's entry the one its `parent` names, and a
 *   page written at a relation archive's key keeps it (`archive`,
 *   D-657). A rename or move changes one record; the pages under a tree's
 *   page follow by their parent ids. A key with a `_` part is hidden, as
 *   a file's would be.
 * - **Versions** are the store's (D-648): a write whose version no longer
 *   matches throws `WriteConflict` and changes nothing.
 *
 * Every write moves the content version on.
 */
final readonly class RecordContentWriter implements ContentWriter
{
	/**
	 * The record key front matter as written is kept under.
	 */
	public const string WRITTEN = 'written';

	public function __construct(
		private RecordStores $stores,
		private EntryLocations $locations,
		private ContentTypes $types,
		private Relations $relations,
		private EntryTargets $targets,
		private FieldContext $context,
		private ContentVersion $version,
		private ClockInterface $clock,
		private AppConfig $app
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function load(string $id): EditableEntry
	{
		$record = $this->stored($id);
		$type   = EntryRecords::text($record, 'type');
		$front  = self::writtenIn($record);

		foreach ($this->refsOf($record->id) as $relation => $targets) {
			$definition = $this->relation($type, $relation);

			if ($definition !== null) {
				$slugs = array_values(array_filter(array_map($this->targets->written(...), $targets), is_string(...)));
				$front[$definition->field] = $definition->multiple ? $slugs : ($slugs[0] ?? null);
			}
		}

		$parent = EntryRecords::text($record, 'parent_id');

		if ($parent !== '' && ! $this->types->get($type)->keysByFolder()) {
			$front['parent'] = $this->targets->written($parent);
		}

		$front[EntryFields::ID] = $record->id;

		return new EditableEntry($record->id, $front, $record->content ?? '', $record->version ?? '');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function loadAt(ContentType $type, string $key): ?EditableEntry
	{
		self::checkKey($key);

		$id = $this->idAt($type, $key);

		return $id === null ? null : $this->load($id);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function create(ContentType $type, string $slug, EntryChanges $changes, ?string $parent = null, ?DateTimeInterface $date = null): string
	{
		self::checkSlug($slug);

		if ($parent !== null) {
			$above = $this->stored($parent);

			if (! $type->keysByFolder() || EntryRecords::text($above, 'type') !== $type->name) {
				throw new WriteException(sprintf('The page to go under isn\'t one of the %s.', $type->labels->items));
			}

			if (EntryRecords::text($above, 'slug') === '') {
				throw new WriteException('That\'s an index page; pages under it go at the top.');
			}
		}

		return $this->write(null, $this->place($type, $slug, $parent), self::apply([], $this->withNew($changes)), $changes->body ?? '');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function createAt(ContentType $type, string $key, EntryChanges $changes): string
	{
		self::checkKey($key);

		if ($key === 'index') {
			return $this->write(null, $this->place($type, ''), self::apply([], $this->withNew($changes)), $changes->body ?? '');
		}

		$slug = basename($key);

		if (! str_contains($key, '/')) {
			return $this->write(null, $this->place($type, $slug), self::apply([], $this->withNew($changes)), $changes->body ?? '');
		}

		if (! $type->keysByFolder()) {
			return $this->write(null, [...$this->place($type, $slug), 'archive' => $key], self::apply([], $this->withNew($changes)), $changes->body ?? '');
		}

		$parent = $this->idAt($type, dirname($key))
			?? throw new WriteException(sprintf('There\'s no page at %s to put %s under.', dirname($key), $key));

		return $this->write(null, $this->place($type, $slug, $parent), self::apply([], $this->withNew($changes)), $changes->body ?? '');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function duplicate(string $id, string $slug, EntryChanges $changes, ?DateTimeInterface $date = null): string
	{
		self::checkSlug($slug);

		$record = $this->stored($id);
		$place  = $this->placeOf($record);

		if ($place['slug'] === '') {
			throw new WriteException('That\'s a landing page; its name is its folder\'s, so it can\'t be copied.');
		}

		$written = $this->load($id)->frontMatter;
		$number  = 1;

		unset($written[EntryFields::ID]);

		do {
			$copy  = $number === 1 ? $slug : "{$slug}-{$number}";
			$moved = [
				...$place,
				'id'          => '',
				'slug'        => $copy,
				'original_id' => null,
				'archive'     => $place['archive'] === null ? null : dirname($place['archive']) . "/{$copy}"
			];
			$number++;
		} while ($this->taken($moved) !== null);

		return $this->write(null, $moved, self::apply($written, $this->withNew($changes, $written)), $changes->body ?? $record->content ?? '');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function update(string $id, EntryChanges $changes, ?string $version = null): string
	{
		$record = $this->current($id, $version);

		if ($changes->isEmpty()) {
			return $record->id;
		}

		$written = $this->load($id)->frontMatter;

		unset($written[EntryFields::ID]);

		return $this->write($record, $this->placeOf($record), self::apply($written, self::withoutId($changes), $this->keys(EntryRecords::text($record, 'type'))), $changes->body ?? $record->content ?? '');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function rename(string $id, string $slug, ?string $version = null): string
	{
		self::checkSlug($slug);

		$record = $this->current($id, $version);
		$place  = $this->placeOf($record);

		if ($place['slug'] === '') {
			throw new WriteException('That\'s a landing page; its name is its folder\'s.');
		}

		if ($place['slug'] === $slug) {
			return $record->id;
		}

		$renamed = [...$place, 'slug' => $slug, 'archive' => $place['archive'] === null ? null : dirname($place['archive']) . "/{$slug}"];

		return $this->write($record, $renamed, $this->writtenOf($record), $record->content ?? '');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function move(string $id, ?string $parent, ?string $version = null): string
	{
		$record = $this->current($id, $version);
		$place  = $this->placeOf($record);
		$type   = $this->types->get($place['type']);
		$title  = EntryRecords::text($record, 'title');
		$name   = $title === '' ? $place['slug'] : $title;

		if (! $type->keysByFolder()) {
			throw new WriteException(sprintf('%s isn\'t a page that can move.', $name));
		}

		if ($place['slug'] === '') {
			throw new WriteException(sprintf('%s is an index page; it stays at the top of its folder.', $name));
		}

		if ($parent !== null) {
			$above = $this->stores->store(EntryTable::table())->find(EntryTable::table(), $parent);

			if ($above === null || EntryRecords::text($above, 'type') !== $type->name || EntryRecords::text($above, 'slug') === '') {
				throw new WriteException(sprintf('That isn\'t one of the %s a page can go under.', $type->labels->items));
			}

			if ($this->isUnder($above->id, $record->id)) {
				throw new WriteException(sprintf('%s can\'t go under itself or a page under it.', $name));
			}

			$parent = $above->id;
		}

		if ($place['parent_id'] === $parent) {
			return $record->id;
		}

		return $this->write($record, [...$place, 'parent_id' => $parent], $this->writtenOf($record), $record->content ?? '');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function trash(string $id, ?string $version = null): string
	{
		return $this->update($id, new EntryChanges([
			'status'             => Status::Trash->value,
			EntryFields::TRASHED => $this->clock->now()->format('Y-m-d H:i:s P')
		]), $version);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function restore(string $id, ?string $version = null, ?Status $status = Status::Draft): string
	{
		return $this->update($id, $status === null
			? new EntryChanges([], ['status', EntryFields::TRASHED])
			: new EntryChanges(['status' => $status->value], [EntryFields::TRASHED]), $version);
	}

	/**
	 * Its refs go with it, both ways: what it links to, and links to it.
	 *
	 * @inheritDoc
	 */
	#[Override]
	public function delete(string $id, ?string $version = null): void
	{
		$record  = $this->current($id, $version);
		$entries = EntryTable::table();
		$refs    = Ref::table($entries->area);
		$store   = $this->store();

		$store->transaction(function () use ($store, $entries, $refs, $record, $version): void {
			foreach ([...$this->refRecords('source_id', $record->id), ...$this->refRecords('target_id', $record->id)] as $ref) {
				$store->delete($refs, $ref->id);
			}

			$this->saved(static fn () => $store->delete($entries, $record->id, $version));
		});

		$this->done();
	}

	/**
	 * Writes an entry: its front matter as written resolved through its
	 * type into its record and refs, saved in one transaction against the
	 * stored version. Returns its id.
	 *
	 * @param  ?Record                                                                                                                                       $old
	 * @param  array{id: string, type: string, language: string, parent_id: ?string, slug: string, original_id: ?string, archive: ?string}                   $place
	 * @param  array<array-key, mixed>                                                                                                                       $written
	 * @throws WriteException
	 */
	private function write(?Record $old, array $place, array $written, string $content): string
	{
		$type    = $this->types->get($place['type']);
		$schema  = $this->types->schema($type->name);
		$front   = [];
		$links   = [];

		// A new entry's id is the one its changes were given.
		if ($place['id'] === '') {
			$place['id'] = is_string($written[EntryFields::ID] ?? null) ? $written[EntryFields::ID] : Uuid::v7($this->clock->now());
		}

		// Only a tree's page is placed by its parent; in other types, the
		// relation that names one does (a hierarchical collection's).
		if (! $type->keysByFolder()) {
			$place['parent_id'] = null;
		}

		// Relations are kept as refs only (D-662); the rest as written.
		foreach (self::kept($type, $this->relations) as $relation) {
			$value = $relation->valueIn($written);

			if ($value === null) {
				continue;
			}

			$resolution = new LinkResolver($this->targets)->resolve($relation, $value, new Refs(), $place['id'], $type->name, $place['language']);

			if ($resolution->problems !== []) {
				throw new WriteException($resolution->problems[0]->message);
			}

			if ($relation->kind === RelationKind::Parent) {
				$place['parent_id'] = $resolution->links[0]->target ?? null;
			} else {
				$links[$relation->name] = array_map(static fn (Link $link): string => $link->target, $resolution->links);
			}

			$front[$relation->field] = $relation->multiple ? $resolution->written : ($resolution->written[0] ?? null);
		}

		$kept = self::asWritten($written, $type, $this->relations);

		foreach ($kept as $key => $value) {
			$front[$key] ??= $value;
		}

		$result    = $schema->resolve($front, $this->context);
		$values    = $result->values;
		$published = is_int($values['published'] ?? null) ? $values['published'] : null;
		$updated   = is_int($values['updated'] ?? null) ? $values['updated'] : ($published ?? $this->clock->now()->getTimestamp());
		$status    = is_string($values['status'] ?? null) ? Status::tryFrom($values['status']) : null;
		$key       = $this->keyOf($place);
		$hidden    = array_any(explode('/', $key), static fn (string $part): bool => str_starts_with($part, '_'));
		$declared  = is_string($values['visibility'] ?? null) ? Visibility::tryFrom($values['visibility']) : null;

		unset($values['slug']);

		$fields = [
			...EntryTable::fields(
				$type->name,
				$place['language'],
				$place['parent_id'],
				$place['slug'],
				$place['original_id'],
				($status ?? Status::Published)->value,
				($hidden ? Visibility::Hidden : $declared ?? Visibility::Public)->value,
				$published,
				$updated,
				is_string($values['title'] ?? null) ? $values['title'] : '',
				$values,
				$result->extra,
				$place['archive']
			),
			self::WRITTEN => $kept
		];

		$taken = $this->taken($place);

		if ($taken !== null && $taken !== $place['id']) {
			throw new WriteException(sprintf('There\'s already a %s at %s.', $type->labels->item, $key === '' ? 'the top of its type' : $key));
		}

		$store   = $this->store();
		$entries = EntryTable::table();
		$refs    = Ref::table($entries->area);

		$store->transaction(function () use ($store, $entries, $refs, $place, $fields, $content, $links, $old): void {
			$this->saved(static fn () => $store->save($entries, new Record($place['id'], $fields, $content), $old?->version));

			foreach ($old === null ? [] : $this->refRecords('source_id', $place['id']) as $ref) {
				$store->delete($refs, $ref->id);
			}

			foreach ($links as $relation => $targets) {
				foreach (array_values(array_unique($targets)) as $position => $target) {
					$store->save($refs, new Ref($place['id'], $relation, $target, $position)->record());
				}
			}
		});

		$this->done();

		return $place['id'];
	}

	/**
	 * Returns front matter as a record keeps it as written (D-662): without
	 * its id, `refs`, or `slug` (the record's own), and without the
	 * relations kept as refs. `storage:copy` keeps files' front matter the
	 * same way.
	 *
	 * @param  array<array-key, mixed> $frontMatter
	 * @return array<string, mixed>
	 */
	public static function asWritten(array $frontMatter, ContentType $type, Relations $relations): array
	{
		$dropped = [EntryFields::ID, Refs::FIELD, 'slug'];

		foreach (self::kept($type, $relations) as $relation) {
			$dropped = [...$dropped, ...$relation->keys()];
		}

		$written = [];

		foreach ($frontMatter as $key => $value) {
			if (! in_array((string) $key, $dropped, true)) {
				$written[(string) $key] = $value;
			}
		}

		return $written;
	}

	/**
	 * Returns a type's relations kept as refs: all but translations, and
	 * a tree's parent, which is its page's column.
	 *
	 * @return list<Relation>
	 */
	private static function kept(ContentType $type, Relations $relations): array
	{
		return array_values(array_filter(
			$relations->for($type->name),
			static fn (Relation $relation): bool => $relation->kind !== RelationKind::Translation && ! ($relation->kind === RelationKind::Parent && $type->keysByFolder())
		));
	}

	/**
	 * Runs a save or delete, turning a version that no longer matches into
	 * a write conflict.
	 *
	 * @param  callable(): mixed $write
	 * @throws WriteConflict
	 */
	private function saved(callable $write): void
	{
		try {
			$write();
		} catch (RecordConflict $e) {
			throw new WriteConflict('The entry changed since it was opened. Reload it, then make your change again.', previous: $e);
		}
	}

	/**
	 * After a write: the content version moves on, and places are worked
	 * out again.
	 */
	private function done(): void
	{
		$this->locations->refresh();
		$this->version->bump();
	}

	/**
	 * Returns a new entry's place: a new id, the site's language, and its
	 * slug, under a parent when given.
	 *
	 * @return array{id: string, type: string, language: string, parent_id: ?string, slug: string, original_id: ?string, archive: ?string}
	 */
	private function place(ContentType $type, string $slug, ?string $parent = null): array
	{
		return [
			'id'          => '',
			'type'        => $type->name,
			'language'    => $this->app->languages->default->code,
			'parent_id'   => $parent,
			'slug'        => $slug,
			'original_id' => null,
			'archive'     => null
		];
	}

	/**
	 * Returns a stored entry's place.
	 *
	 * @return array{id: string, type: string, language: string, parent_id: ?string, slug: string, original_id: ?string, archive: ?string}
	 */
	private function placeOf(Record $record): array
	{
		$text = static fn (string $key): ?string => EntryRecords::text($record, $key) === '' ? null : EntryRecords::text($record, $key);

		return [
			'id'          => $record->id,
			'type'        => EntryRecords::text($record, 'type'),
			'language'    => EntryRecords::text($record, 'language'),
			'parent_id'   => $text('parent_id'),
			'slug'        => EntryRecords::text($record, 'slug'),
			'original_id' => $text('original_id'),
			'archive'     => $text('archive')
		];
	}

	/**
	 * Returns the key a place gives an entry: an archive's page its
	 * place, a nesting type's page its parent's key and its slug, any
	 * other its slug.
	 *
	 * @param array{id: string, type: string, language: string, parent_id: ?string, slug: string, original_id: ?string, archive: ?string} $place
	 */
	private function keyOf(array $place): string
	{
		if ($place['archive'] !== null) {
			return $place['archive'];
		}

		$above = $place['parent_id'] === null || ! $this->types->get($place['type'])->keysByFolder() ? null : $this->locations->key($place['parent_id']);

		return $above === null || $above === '' ? $place['slug'] : "{$above}/{$place['slug']}";
	}

	/**
	 * Returns the id of the entry already at a place (the same type,
	 * language, and slug, among its siblings in a nesting type, or at an
	 * archive's key), or `null`.
	 *
	 * @param array{id: string, type: string, language: string, parent_id: ?string, slug: string, original_id: ?string, archive: ?string} $place
	 */
	private function taken(array $place): ?string
	{
		$query = new RecordQuery()
			->where('type', Operator::Equal, $place['type'])
			->where('language', Operator::Equal, $place['language'])
			->withoutContent();

		if ($place['archive'] !== null) {
			$query = $query->where('archive', Operator::Equal, $place['archive']);
		} else {
			$query = $query->where('slug', Operator::Equal, $place['slug'])->where('archive', Operator::Null);

			if ($this->types->get($place['type'])->keysByFolder() && $place['slug'] !== '') {
				$query = $place['parent_id'] === null
					? $query->where('parent_id', Operator::Null)
					: $query->where('parent_id', Operator::Equal, $place['parent_id']);
			}
		}

		foreach ($this->store()->select(EntryTable::table(), $query)->records as $found) {
			if ($found->id !== $place['id']) {
				return $found->id;
			}
		}

		return null;
	}

	/**
	 * Returns the id of a type's entry at a key in the site's language,
	 * or `null`.
	 */
	private function idAt(ContentType $type, string $key): ?string
	{
		$language = $this->app->languages->default->code;

		if ($key === 'index') {
			return $this->taken([...$this->place($type, ''), 'id' => '', 'language' => $language]);
		}

		foreach ($this->locations->idsWithKey($key) as $id) {
			$record = $this->store()->find(EntryTable::table(), $id);

			if ($record !== null && EntryRecords::text($record, 'type') === $type->name && EntryRecords::text($record, 'language') === $language) {
				return $record->id;
			}
		}

		return null;
	}

	/**
	 * Returns whether an entry is another or under it, by parents.
	 */
	private function isUnder(string $id, string $ancestor): bool
	{
		$seen = [];

		while ($id !== '' && ! isset($seen[$id])) {
			if ($id === $ancestor) {
				return true;
			}

			$seen[$id] = true;
			$record    = $this->store()->find(EntryTable::table(), $id);
			$id        = $record === null ? '' : EntryRecords::text($record, 'parent_id');
		}

		return false;
	}

	/**
	 * Returns a stored entry, checked against a version.
	 *
	 * @throws WriteConflict
	 * @throws WriteException
	 */
	private function current(string $id, ?string $version): Record
	{
		$record = $this->stored($id);

		if ($version !== null && $record->version !== $version) {
			throw new WriteConflict('The entry changed since it was opened. Reload it, then make your change again.');
		}

		return $record;
	}

	/**
	 * Returns a stored entry, with its content.
	 *
	 * @throws WriteException When there's none.
	 */
	private function stored(string $id): Record
	{
		return $this->store()->find(EntryTable::table(), $id)
			?? throw new WriteException(sprintf('There\'s no entry with the id "%s".', $id));
	}

	/**
	 * Returns an entry's front matter as written, without its relations:
	 * kept under `written`, or, for a record written by another driver
	 * (a copy from files), its normalized front matter.
	 *
	 * @return array<array-key, mixed>
	 */
	private static function writtenIn(Record $record): array
	{
		$written = $record->fields[self::WRITTEN] ?? null;

		if (is_array($written)) {
			return $written;
		}

		$front = EntryRecords::front($record);

		unset($front[Refs::FIELD], $front[EntryFields::ID]);

		return $front;
	}

	/**
	 * Returns a stored entry's front matter as written, with its
	 * relations, for writing it again.
	 *
	 * @return array<array-key, mixed>
	 */
	private function writtenOf(Record $record): array
	{
		$front = $this->load($record->id)->frontMatter;

		unset($front[EntryFields::ID]);

		return $front;
	}

	/**
	 * Returns an entry's targets by relation, in order.
	 *
	 * @return array<string, list<string>>
	 */
	private function refsOf(string $id): array
	{
		$grouped = [];

		foreach ($this->refRecords('source_id', $id) as $record) {
			$ref = Ref::fromRecord($record);

			$grouped[$ref->relation][$ref->position] = $ref->target;
		}

		return array_map(static function (array $targets): array {
			ksort($targets);

			return array_values($targets);
		}, $grouped);
	}

	/**
	 * Returns the refs with an entry at one end.
	 *
	 * @return list<Record>
	 */
	private function refRecords(string $end, string $id): array
	{
		$table = Ref::table(EntryTable::table()->area);

		return $this->stores->store($table)->select($table, new RecordQuery()->where($end, Operator::Equal, strtolower($id)))->records;
	}

	/**
	 * Returns one of a type's relations by name, when it's kept as refs.
	 */
	private function relation(string $type, string $name): ?Relation
	{
		return array_find(
			$this->relations->for($type),
			static fn (Relation $relation): bool => $relation->name === $name && ! in_array($relation->kind, [RelationKind::Parent, RelationKind::Translation], true)
		);
	}

	/**
	 * Returns the content store.
	 */
	private function store(): RecordStore
	{
		return $this->stores->store(EntryTable::table());
	}

	/**
	 * Returns changes for a new entry: its id (one the changes give, when
	 * it's a UUID no entry has, D-653; else a new one), and a `published`
	 * date of now in the site's time zone when neither the changes nor
	 * the front matter it starts from (a copy's) has one (D-514).
	 *
	 * @param array<array-key, mixed> $frontMatter
	 */
	private function withNew(EntryChanges $changes, array $frontMatter = []): EntryChanges
	{
		$given = $changes->set[EntryFields::ID] ?? null;
		$id    = is_string($given) && Uuid::isValid($given) && $this->store()->find(EntryTable::table(), $given) === null
			? strtolower($given)
			: Uuid::v7($this->clock->now());
		$set   = self::withoutId($changes)->set;

		if (array_intersect_key([...$frontMatter, ...$set], ['published' => true, 'date' => true]) === []) {
			$set['published'] = $this->clock->now()->setTimezone($this->app->timezone())->format('Y-m-d H:i:s P');
		}

		return new EntryChanges([...$set, EntryFields::ID => $id], $changes->remove, $changes->body);
	}

	/**
	 * Returns changes that leave the id alone: only the writer gives
	 * entries ids.
	 */
	private static function withoutId(EntryChanges $changes): EntryChanges
	{
		$set = $changes->set;

		unset($set[EntryFields::ID]);

		return new EntryChanges($set, array_values(array_diff($changes->remove, [EntryFields::ID])), $changes->body);
	}

	/**
	 * Applies changes to front matter as written: each value set where its
	 * field's name or an alias already is (else under its name), and each
	 * removed key gone under every name it has. A new entry's id is the
	 * place's.
	 *
	 * @param  array<array-key, mixed>          $written
	 * @param  ?Closure(string): list<string> $keys
	 * @return array<array-key, mixed>
	 */
	private static function apply(array $written, EntryChanges $changes, ?Closure $keys = null): array
	{
		$keys ??= static fn (string $name): array => [$name];

		foreach ($changes->remove as $name) {
			foreach ($keys($name) as $key) {
				unset($written[$key]);
			}
		}

		foreach ($changes->set as $name => $value) {
			$name  = (string) $name;
			$found = array_find($keys($name), static fn (string $key): bool => array_key_exists($key, $written)) ?? $name;

			$written[$found] = $value;
		}

		return $written;
	}

	/**
	 * Returns a closure giving a field name's keys under a type's schema:
	 * the name, then its aliases.
	 *
	 * @return Closure(string): list<string>
	 */
	private function keys(string $type): Closure
	{
		$schema = $this->types->find($type) === null ? null : $this->types->schema($type);

		return static function (string $name) use ($schema): array {
			$field = $schema?->field($name);

			return $field === null ? [$name] : array_values(array_unique([$name, $field->name, ...$field->aliases]));
		};
	}

	/**
	 * Refuses a slug that isn't one.
	 *
	 * @throws WriteException
	 */
	private static function checkSlug(string $slug): void
	{
		if (! Slug::isSlug($slug)) {
			throw new WriteException(sprintf('"%s" isn\'t a slug; try "%s".', $slug, Slug::from($slug)));
		}
	}

	/**
	 * Refuses a key a page can't be written at.
	 *
	 * @throws WriteException
	 */
	private static function checkKey(string $key): void
	{
		if (! FilesystemWriter::isPageKey($key)) {
			throw new WriteException(sprintf('"%s" isn\'t a page key: slugs separated by "/", each may start with "_".', $key));
		}
	}
}
