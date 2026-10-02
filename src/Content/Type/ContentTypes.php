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
use Blush\Content\EntryFields;
use Blush\Field\Field;
use Blush\Field\FieldFactory;
use Blush\Field\FieldSet;
use Blush\Field\FieldSets;
use Blush\Field\Fields\MediaField;
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
 * Each type's full schema is the built-in entry fields, then every
 * taxonomy's term field (which may not reuse a built-in name or alias),
 * then the type's people fields (D-351), or a profile's `avatar`, then
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
	 * @param ?string                    $home    The home page's type.
	 * @param FieldSets                  $sets    The site's field sets.
	 * @param list<string>               $overrides Types from code that a data file changes (D-349).
	 */
	public function __construct(
		private readonly array $types,
		private readonly array $origins = [],
		public readonly ?string $home = null,
		public readonly FieldSets $sets = new FieldSets(),
		private readonly array $overrides = []
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
	 * Returns the home page's type, if the home page shows a collection.
	 */
	public function homeType(): ?ContentType
	{
		return $this->home === null ? null : $this->find($this->home);
	}

	/**
	 * Returns the taxonomies, keyed by name.
	 *
	 * @return array<string, Taxonomy>
	 */
	public function taxonomies(): array
	{
		return array_filter($this->types, static fn (ContentType $type): bool => $type instanceof Taxonomy);
	}

	/**
	 * Returns the profiles type (D-351), or `null` when the site has none.
	 */
	public function profiles(): ?Profiles
	{
		return array_find($this->types, static fn (ContentType $type): bool => $type instanceof Profiles);
	}

	/**
	 * Returns the types other entries reference as terms (the taxonomies
	 * and the profiles type), keyed by name.
	 *
	 * @return array<string, Taxonomy|Profiles>
	 */
	public function termTypes(): array
	{
		return array_filter($this->types, static fn (ContentType $type): bool => $type instanceof Taxonomy || $type instanceof Profiles);
	}

	/**
	 * Returns the types whose entries credit people, keyed by name.
	 *
	 * @return array<string, ContentType>
	 */
	public function crediting(): array
	{
		return array_filter($this->types, static fn (ContentType $type): bool => $type->credits());
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

		return $this->schemas[$name] = $schema;
	}

	/**
	 * Returns a type's schema before its field sets: the built-in entry
	 * fields, every taxonomy's term field, the type's people fields (when
	 * the site has a profiles type) or a profile's `avatar`, a
	 * hierarchical taxonomy's `parent`, then the type's own fields.
	 *
	 * @throws InvalidContentType When the fields clash.
	 */
	public function ownSchema(string $name): Schema
	{
		$type = $this->get($name);

		try {
			$terms   = array_values(array_map(static fn (Taxonomy $taxonomy): Field => $taxonomy->termField(), $this->taxonomies()));
			$profiles = $this->profiles()?->name;
			$people   = $profiles === null ? [] : array_values(array_map(static fn (PeopleField $field): Field => $field->referenceField($profiles), $type->people));
			$avatar   = $type instanceof Profiles ? [new MediaField('avatar')->described('A portrait, shown beside the name; without one, initials stand in.')] : [];
			$parent   = $type instanceof Taxonomy ? $type->parentField() : null;

			return new Schema([
				...array_values(EntryFields::schema()->fields),
				...$terms,
				...$people,
				...$avatar,
				...($parent === null ? [] : [$parent])
			])->merge($type->schema);
		} catch (InvalidSchema $e) {
			throw new InvalidContentType(sprintf('Content type "%s" has clashing fields: %s', $name, $e->getMessage()), previous: $e);
		}
	}

	/**
	 * Returns the types as an array for a compiled cache.
	 *
	 * @return array{types: list<array<string, mixed>>, origins: array<string, string>, overrides: list<string>, home: ?string, sets: list<array<string, mixed>>}
	 */
	public function toArray(): array
	{
		return [
			'types'     => array_values(array_map(static fn (ContentType $type): array => $type->toArray(), $this->types)),
			'origins'   => array_map(static fn (TypeOrigin $origin): string => $origin->value, $this->origins),
			'overrides' => $this->overrides,
			'home'      => $this->home,
			'sets'      => $this->sets->toArray()
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

		return new self($types, $origins, is_string($home) ? $home : null, $sets, $overrides);
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
