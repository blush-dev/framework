<?php

/**
 * Content types.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Override;
use Blush\Content\Entry\Position;
use Blush\Content\EntryFields;
use Blush\Content\Relation\InvalidRelation;
use Blush\Content\Relation\Inverse;
use Blush\Content\Relation\Relation;
use Blush\Content\Relation\RelationKind;
use Blush\Content\Relation\RelationOrigin;
use Blush\Content\Relation\Refs;
use Blush\Extension\DefinitionClash;
use Blush\Field\Field;
use Blush\Field\FieldFactory;
use Blush\Field\FieldSet;
use Blush\Field\FieldSets;
use Blush\Field\Fields\MediaField;
use Blush\Field\Fields\NumberField;
use Blush\Field\Fields\ReferenceField;
use Blush\Field\InvalidSchema;
use Blush\Field\Schema;

/**
 * The site's resolved content types, from every source (`ContentTypeLoader`
 * builds and checks them). Types are found by name, by folder, or for a
 * file under `user/content`: a file belongs to the type whose folder is
 * the nearest one above it, and anything else is a page. So
 * `writing/forms/essay.md` is a `literary_form` even though `writing`
 * belongs to `literature`, and a bundle's `_posts/hello/index.md` is a
 * post.
 *
 * The types carry the site's relation definitions (D-593: from
 * extensions, config, and `user/data/relations`), since a classify
 * relation adds its field to the types it's from.
 *
 * Each type's full schema is the built-in entry fields, then the term
 * field of every relation from it (which may not reuse a built-in name
 * or alias; a credit's people too, D-602), or a profile's `avatar`, then
 * the type's own fields (which may replace any of them), then the fields
 * of the field sets attached to it (`type:{name}`, D-337), in set name
 * order, which may not reuse any name or alias before them
 * (`FieldSets::schemaFor()`).
 *
 * @implements IteratorAggregate<string, ContentType>
 */
final class ContentTypes implements IteratorAggregate, Countable
{
	/**
	 * Type names keyed by folder.
	 *
	 * @var array<string, string>
	 */
	private array $folders = [];

	/**
	 * Full schemas built so far, keyed by type name.
	 *
	 * @var array<string, Schema>
	 */
	private array $schemas = [];

	/**
	 * @param array<string, ContentType> $types   Keyed by name.
	 * @param array<string, TypeOrigin>  $origins Keyed by name.
	 * @param ?string                    $home    The homepage's type.
	 * @param FieldSets                  $sets    The site's field sets.
	 * @param list<string>               $overrides Types from code that a data file changes (D-349).
	 * @param array<string, Relation>     $relations Relation definitions, keyed by name (D-593).
	 * @param array<string, RelationOrigin> $relationOrigins Keyed by name.
	 * @param list<string>               $legacy    Data types still in the taxonomy form, read as a collection and its relation until they're migrated (D-591).
	 * @param list<DefinitionClash>      $clashes   Types and relations two extensions define by one name; the first of each is kept (D-597).
	 */
	public function __construct(
		private readonly array $types,
		private readonly array $origins = [],
		public readonly ?string $home = null,
		public readonly FieldSets $sets = new FieldSets(),
		private readonly array $overrides = [],
		private readonly array $relations = [],
		private readonly array $relationOrigins = [],
		public readonly array $legacy = [],
		public readonly array $clashes = []
	) {
		foreach ($types as $name => $type) {
			$this->folders[$type->folder] = $name;
		}
	}

	/**
	 * Returns a type by name.
	 *
	 * @throws InvalidContentType When there's no such type.
	 */
	public function get(string $name): ContentType
	{
		return $this->types[$name] ?? throw new InvalidContentType(sprintf('There is no "%s" content type.', $name));
	}

	/**
	 * Returns a type by name, or `null`.
	 */
	public function find(string $name): ?ContentType
	{
		return $this->types[$name] ?? null;
	}

	/**
	 * Returns whether a type exists.
	 */
	public function has(string $name): bool
	{
		return isset($this->types[$name]);
	}

	/**
	 * Returns every type, keyed by name.
	 *
	 * @return array<string, ContentType>
	 */
	public function all(): array
	{
		return $this->types;
	}

	/**
	 * Returns where a type was defined.
	 */
	public function origin(string $name): TypeOrigin
	{
		return $this->origins[$name] ?? TypeOrigin::Config;
	}

	/**
	 * Returns whether a type from code has a data file in
	 * `user/data/types` changing it (D-349).
	 */
	public function isOverridden(string $name): bool
	{
		return in_array($name, $this->overrides, true);
	}

	/**
	 * Returns whether the admin may change a type: one defined in data, or
	 * a type from code that a data file may override (D-311, D-349,
	 * `ContentType::isOverridable()`). The pages and profiles types
	 * defined in code stay as they are.
	 */
	public function isEditable(string $name): bool
	{
		$origin = $this->origin($name);

		return $origin === TypeOrigin::Data
			|| (($origin === TypeOrigin::Config || $origin === TypeOrigin::Extension) && ($this->find($name)?->isOverridable() ?? false));
	}

	/**
	 * Returns the homepage's type, if the homepage shows a collection.
	 */
	public function homeType(): ?ContentType
	{
		return $this->home === null ? null : $this->find($this->home);
	}

	/**
	 * Returns the relation definitions, keyed by name (D-593).
	 *
	 * @return array<string, Relation>
	 */
	public function relations(): array
	{
		return $this->relations;
	}

	/**
	 * Returns where a relation definition comes from, or `null` when
	 * there's none by that name.
	 */
	public function relationOrigin(string $name): ?RelationOrigin
	{
		return isset($this->relations[$name]) ? $this->relationOrigins[$name] ?? RelationOrigin::Config : null;
	}

	/**
	 * Returns the classify relations, keyed by name, which is the type
	 * each files entries under (D-593): what taxonomies were.
	 *
	 * @return array<string, Relation>
	 */
	public function classifications(): array
	{
		return array_filter($this->relations, static fn (Relation $relation): bool => $relation->kind === RelationKind::Classify);
	}

	/**
	 * Returns the classify relation that files entries under a type, or
	 * `null` when none does.
	 */
	public function classification(string $type): ?Relation
	{
		return $this->classifications()[$type] ?? null;
	}

	/**
	 * Returns the relations whose inverse archive is on a type's own
	 * entries' pages (`page: true`, D-596, D-602): the classify relation
	 * filing entries under it first, then any reference to it alone, by
	 * name.
	 *
	 * @return list<Relation>
	 */
	public function archivedTo(string $name): array
	{
		$found = [];

		foreach ($this->relations as $relation) {
			if (
				! $relation->kind->isWithinType()
				&& $relation->to === [$name]
				&& $relation->inverse !== false
				&& $relation->inverse->page
			) {
				$found[] = $relation;
			}
		}

		usort($found, static fn (Relation $a, Relation $b): int => [$b->kind === RelationKind::Classify, $a->name] <=> [$a->kind === RelationKind::Classify, $b->name]);

		return $found;
	}

	/**
	 * Returns the index keys an entry's page lists what links to it by
	 * (`Relation::termKey()`), for each relation archived on it.
	 *
	 * @return list<string>
	 */
	public function termKeys(string $name): array
	{
		return array_values(array_filter(array_map(static fn (Relation $relation): ?string => $relation->termKey(), $this->archivedTo($name))));
	}

	/**
	 * Returns the relations from a type with archives under it, by name
	 * (an inverse `archive` word, D-596, D-602): a classify, credit, or
	 * reference relation to one type, from a public type with URLs, such
	 * as `movie.actors` with `archive: actors` (`/movies/actors/tom`), or
	 * `post.authors` (`/archives/authors/jane`).
	 *
	 * @return array<string, Relation>
	 */
	public function relationArchives(ContentType $type): array
	{
		if (! $type->public || ! $type->hasUrls()) {
			return [];
		}

		return array_filter($this->relations, fn (Relation $relation): bool => ! $relation->kind->isWithinType()
			&& $relation->isFrom($type->name)
			&& count($relation->to) === 1
			&& $this->find($relation->to[0]) !== null
			&& $relation->inverse !== false
			&& $relation->inverse->archive !== false);
	}

	/**
	 * Returns the default paths of a type's relation archives' route keys,
	 * under each archive word: `{name}.collection` (`actors`, every
	 * target linked to), `{name}.single` (`actors/{target}`), its
	 * `.paged`, and its feeds. A type's URL `paths` can move them.
	 *
	 * @return array<string, string>
	 */
	public function relationPaths(ContentType $type): array
	{
		$paths = [];

		foreach ($this->relationArchives($type) as $name => $relation) {
			$word = $relation->inverse === false ? '' : (string) $relation->inverse->archive;

			$paths += [
				"{$name}.collection"       => $word,
				"{$name}.single"           => "{$word}/{target}",
				"{$name}.single.paged"     => "{$word}/{target}/page/{page}",
				"{$name}.single.feed.json" => "{$word}/{target}/feed/json",
				"{$name}.single.feed.atom" => "{$word}/{target}/feed/atom",
				"{$name}.single.feed"      => "{$word}/{target}/feed"
			];
		}

		return $paths;
	}

	/**
	 * Returns a type's full route pattern for a route key, its relation
	 * archives' included (`ContentType::routePattern()`).
	 */
	public function routePattern(ContentType $type, string $key): ?string
	{
		return $type->routePattern($key, $this->relationPaths($type));
	}

	/**
	 * Returns whether a type's entries have pages of their own listing
	 * what links to them (a relation's inverse archive on the target,
	 * D-593, D-596): a term's, or a person's in a `movie.actors` relation
	 * with `page: true`. It needs a collection with URLs.
	 */
	public function hasTermPages(string $name): bool
	{
		$type = $this->find($name);

		return $type instanceof Collection && $type->hasUrls() && $this->archivedTo($name) !== [];
	}

	/**
	 * Returns a term page's 1.x query arguments, for `Query::fromArray()`,
	 * before the term itself is matched: the types its relations link
	 * from (each inverse's `types`, else its `from`), listed as the first
	 * relation's inverse `listing` says.
	 *
	 * @return array<string, mixed>
	 */
	public function termArguments(string $name): array
	{
		$relations = $this->archivedTo($name);

		if ($relations === []) {
			return [];
		}

		$types = [];

		foreach ($relations as $relation) {
			$inverse = $relation->inverse === false ? new Inverse() : $relation->inverse;
			$from    = $inverse->types === [] ? $relation->from : $inverse->types;

			// Every type, for one that names none.
			if ($from === []) {
				$types = null;
				break;
			}

			$types = array_values(array_unique([...$types, ...$from]));
		}

		$first = $relations[0]->inverse === false ? new Inverse() : $relations[0]->inverse;

		return [...($types === null ? [] : ['type' => $types]), ...$first->listing->arguments()];
	}

	/**
	 * Returns whether a type's entries name a parent entry of the type
	 * (a hierarchical collection's terms), rather than nesting by folder.
	 */
	public function nestsByParent(string $name): bool
	{
		$type = $this->find($name);

		return $type instanceof Collection && $type->hierarchical;
	}

	/**
	 * Returns the types whose entries are terms: what a classify relation
	 * files entries under, keyed by name.
	 *
	 * @return array<string, ContentType>
	 */
	public function classifying(): array
	{
		return array_intersect_key($this->types, $this->classifications());
	}

	/**
	 * Returns the profiles type (D-351), or `null` when the site has none.
	 */
	public function profiles(): ?Profiles
	{
		return array_find($this->types, static fn (ContentType $type): bool => $type instanceof Profiles);
	}

	/**
	 * Returns the types other entries reference as terms (the types a
	 * classify relation files entries under, and the profiles type), keyed
	 * by name.
	 *
	 * @return array<string, ContentType>
	 */
	public function termTypes(): array
	{
		return array_filter($this->types, fn (ContentType $type): bool => $type instanceof Profiles || isset($this->classifications()[$type->name]));
	}

	/**
	 * Returns whether other entries reference a type's entries as terms,
	 * which the index keeps a reverse lookup for.
	 */
	public function isTermType(string $name): bool
	{
		return isset($this->termTypes()[$name]);
	}

	/**
	 * Returns the credit relations a type's entries credit people through
	 * (D-602), keyed by name.
	 *
	 * @return array<string, Relation>
	 */
	public function credits(string $type): array
	{
		return array_filter($this->relations, static fn (Relation $relation): bool => $relation->kind === RelationKind::Credit && $relation->isFrom($type));
	}

	/**
	 * Returns a type's byline (D-602): the credit relation its `byline`
	 * names, else its only one, or `null`.
	 */
	public function byline(string $type): ?Relation
	{
		$credits = $this->credits($type);
		$named   = $this->find($type)?->byline;

		return $named === null ? (count($credits) === 1 ? array_first($credits) : null) : $credits[$named] ?? null;
	}

	/**
	 * Returns the types whose entries credit people, keyed by name.
	 *
	 * @return array<string, ContentType>
	 */
	public function crediting(): array
	{
		return array_filter($this->types, fn (ContentType $type): bool => $this->credits($type->name) !== []);
	}

	/**
	 * Returns the type whose folder is exactly `$folder`.
	 */
	public function byFolder(string $folder): ?ContentType
	{
		$name = $this->folders[trim($folder, '/')] ?? null;

		return $name === null ? null : $this->types[$name];
	}

	/**
	 * Returns the folder path under `user/content` that a path the page
	 * catch-all serves points to (D-386): `_docs/install` for
	 * `docs/install` when a type served as pages has the folder `_docs`.
	 * Other paths are their own.
	 */
	public function folderPath(string $path): string
	{
		$path  = trim($path, '/');
		$types = array_filter($this->types, static fn (ContentType $type): bool => $type->servedAsPages() && $type->folder !== '');

		usort($types, static fn (ContentType $a, ContentType $b): int => strlen($b->pagePath()) <=> strlen($a->pagePath()));

		foreach ($types as $type) {
			$base = $type->pagePath();

			if ($path === $base || str_starts_with($path, "{$base}/")) {
				return $type->folder . substr($path, strlen($base));
			}
		}

		return $path;
	}

	/**
	 * Returns the type a file under `user/content` belongs to, from its
	 * path relative to that folder.
	 *
	 * @throws InvalidContentType When no type claims the content root.
	 */
	public function forFile(string $relativePath): ContentType
	{
		$directory = dirname(trim($relativePath, '/'));
		$directory = $directory === '.' ? '' : $directory;

		while (true) {
			$type = $this->byFolder($directory);

			if ($type !== null) {
				return $type;
			}

			if ($directory === '') {
				throw new InvalidContentType('No content type claims the content root.');
			}

			$parent    = dirname($directory);
			$directory = $parent === '.' ? '' : $parent;
		}
	}

	/**
	 * Returns the field sets attached to a type, in name order.
	 *
	 * @return list<FieldSet>
	 * @throws InvalidContentType When there's no such type.
	 */
	public function setsFor(string $name): array
	{
		return $this->sets->for(ContentTypeTarget::keyFor($this->get($name)->name));
	}

	/**
	 * Returns a type's full schema: its own (`ownSchema()`), then its
	 * field sets' fields (`FieldSets::schemaFor()`).
	 *
	 * @throws InvalidContentType When the fields clash, or the type won't
	 *                            take a set's field.
	 */
	public function schema(string $name): Schema
	{
		if (isset($this->schemas[$name])) {
			return $this->schemas[$name];
		}

		try {
			$schema = $this->sets->schemaFor(new ContentTypeTarget($this, $this->get($name)));
		} catch (InvalidSchema $e) {
			throw $e->getPrevious() instanceof InvalidContentType
				? $e->getPrevious()
				: new InvalidContentType($e->getMessage(), previous: $e);
		}

		// The entry's id is no field's (D-477), nor are its links' ids (D-589).
		if ($schema->field(EntryFields::ID) !== null) {
			throw new InvalidContentType(sprintf('Content type "%s" has a field (or alias) named "%s", which is reserved for the entry\'s id; rename it.', $name, EntryFields::ID));
		}

		if ($schema->field(Refs::FIELD) !== null) {
			throw new InvalidContentType(sprintf('Content type "%s" has a field (or alias) named "%s", which is reserved for the ids of the entry\'s links; rename it.', $name, Refs::FIELD));
		}

		return $this->schemas[$name] = $schema;
	}

	/**
	 * Returns a type's schema before its field sets: the built-in entry
	 * fields, the field of each relation the site defines from it (D-593:
	 * a classify relation's terms, or a credit's profiles, say), a
	 * profile's `avatar`, a hierarchical collection's `parent`, a tree's or a
	 * positioned collection's `position` (D-412), then the type's own
	 * fields.
	 *
	 * @throws InvalidContentType When the fields clash.
	 */
	public function ownSchema(string $name): Schema
	{
		$type = $this->get($name);

		try {
			$terms    = array_values(array_map(
				static fn (Relation $relation): Field => new ReferenceField($relation->field, count($relation->to) === 1 ? $relation->to[0] : '', $relation->multiple)
					->aliases(...$relation->aliases)
					->required($relation->isRequired()),
				array_filter($this->relations, static fn (Relation $relation): bool => ! $relation->kind->isWithinType() && $relation->isFrom($name))
			));
			$avatar   = $type instanceof Profiles ? [new MediaField('avatar')->described('A portrait, shown beside the name; without one, initials stand in.')] : [];
			$parent   = $type instanceof Collection ? $type->parentField() : null;
			$position = $type instanceof Tree || ($type instanceof Collection && $type->isPositioned())
				? [new NumberField(Position::FIELD, integer: true)->described(sprintf('Its place among its sibling %s, lowest first; those without one follow, by title.', $type->labels->items))]
				: [];

			return new Schema([
				...array_values(EntryFields::schema()->fields),
				...$terms,
				...$avatar,
				...($parent === null ? [] : [$parent]),
				...$position
			])->merge($type->schema);
		} catch (InvalidSchema $e) {
			throw new InvalidContentType(sprintf('Content type "%s" has clashing fields: %s', $name, $e->getMessage()), previous: $e);
		}
	}

	/**
	 * Returns the types as an array for a compiled cache.
	 *
	 * @return array{types: list<array<string, mixed>>, origins: array<string, string>, overrides: list<string>, home: ?string, sets: list<array<string, mixed>>, relations: list<array<string, mixed>>, relationOrigins: array<string, string>, legacy: list<string>, clashes: list<array{kind: string, name: string, kept: string, dropped: string}>}
	 */
	public function toArray(): array
	{
		return [
			'types'           => array_values(array_map(static fn (ContentType $type): array => $type->toArray(), $this->types)),
			'origins'         => array_map(static fn (TypeOrigin $origin): string => $origin->value, $this->origins),
			'overrides'       => $this->overrides,
			'home'            => $this->home,
			'sets'            => $this->sets->toArray(),
			'relations'       => array_values(array_map(static fn (Relation $relation): array => $relation->toArray(), $this->relations)),
			'relationOrigins' => array_map(static fn (RelationOrigin $origin): string => $origin->value, $this->relationOrigins),
			'legacy'          => $this->legacy,
			'clashes'         => array_map(static fn (DefinitionClash $clash): array => $clash->toArray(), $this->clashes)
		];
	}

	/**
	 * Rebuilds the types from `toArray()`'s output.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidContentType
	 */
	public static function fromArray(array $data, FieldFactory $fields): self
	{
		$types   = [];
		$origins = [];

		foreach (is_array($data['types'] ?? null) ? $data['types'] : [] as $definition) {
			if (is_array($definition)) {
				$type               = ContentType::fromArray($definition, $fields);
				$types[$type->name] = $type;
			}
		}

		foreach (is_array($data['origins'] ?? null) ? $data['origins'] : [] as $name => $origin) {
			if (is_string($name) && is_string($origin) && ($case = TypeOrigin::tryFrom($origin)) !== null) {
				$origins[$name] = $case;
			}
		}

		$home = $data['home'] ?? null;

		try {
			$sets = FieldSets::fromArray(is_array($data['sets'] ?? null) ? $data['sets'] : [], $fields);
		} catch (InvalidSchema $e) {
			throw new InvalidContentType($e->getMessage(), previous: $e);
		}

		$overrides = array_values(array_filter(is_array($data['overrides'] ?? null) ? $data['overrides'] : [], is_string(...)));
		$relations = [];
		$relationOrigins = [];

		try {
			foreach (is_array($data['relations'] ?? null) ? $data['relations'] : [] as $definition) {
				if (is_array($definition)) {
					$relation                    = Relation::fromArray($definition);
					$relations[$relation->name] = $relation;
				}
			}
		} catch (InvalidRelation $e) {
			throw new InvalidContentType($e->getMessage(), previous: $e);
		}

		foreach (is_array($data['relationOrigins'] ?? null) ? $data['relationOrigins'] : [] as $name => $origin) {
			if (is_string($name) && is_string($origin) && ($case = RelationOrigin::tryFrom($origin)) !== null) {
				$relationOrigins[$name] = $case;
			}
		}

		$legacy = array_values(array_filter(is_array($data['legacy'] ?? null) ? $data['legacy'] : [], is_string(...)));

		$clashes = array_values(array_filter(array_map(DefinitionClash::fromArray(...), is_array($data['clashes'] ?? null) ? $data['clashes'] : [])));

		return new self($types, $origins, is_string($home) ? $home : null, $sets, $overrides, $relations, $relationOrigins, $legacy, $clashes);
	}

	/**
	 * @inheritDoc
	 *
	 * @return ArrayIterator<string, ContentType>
	 */
	#[Override]
	public function getIterator(): ArrayIterator
	{
		return new ArrayIterator($this->types);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function count(): int
	{
		return count($this->types);
	}
}
