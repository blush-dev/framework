<?php

/**
 * Content type.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use NoDiscard;
use Blush\Content\Query\Order;
use Blush\Field\Definition;
use Blush\Field\Field;
use Blush\Field\FieldFactory;
use Blush\Field\Fields\ReferenceField;
use Blush\Field\InvalidSchema;
use Blush\Field\Schema;

/**
 * A content type: the entries in one folder of `user/content`, how they're
 * routed, listed, and fed, and the fields they have. The kinds are final
 * classes (D-157): `Collection` for listed entries such as posts,
 * `Taxonomy` for terms that group other entries, `Tree` for entries
 * that nest by folder (the built-in page type, which claims the content
 * root, is one; D-386), and `Profiles` for the
 * people entries credit (D-351). One model serves types from code and
 * from data (D-042). A type credits people through its people fields
 * (`PeopleField`), such as a collection's `authors`.
 *
 * `fromArray()` builds any kind from a definition array (its `kind`, or
 * 1.x's `taxonomy: true`) and also accepts every 1.x option name
 * (D-078), such as `path`, `routing`, `date_archives`, and
 * `term_collect`.
 */
abstract readonly class ContentType
{
	/**
	 * The folder under `user/content`, without slashes. The page type's
	 * is `''`, the content root.
	 */
	public string $folder;

	/**
	 * The type's own fields, beyond the built-in ones.
	 */
	public Schema $schema;

	/**
	 * What people call the type and its entries ("Literary genres", "New
	 * literary genre"), such as in the admin.
	 */
	public TypeLabels $labels;

	/**
	 * What the type is for, in a sentence, such as the admin's empty
	 * list; `''` for none.
	 */
	public string $description;

	/**
	 * The name of an icon the admin shows the type with (an `icon`
	 * directive name, such as `film`), or `null` for its kind's.
	 */
	public ?string $icon;

	/**
	 * The ways the type's entries credit people (D-351), keyed by field.
	 *
	 * @var array<string, PeopleField>
	 */
	public array $people;

	/**
	 * @param  string            $name         Lowercase letters, digits, and underscores.
	 * @param  ?string           $folder       Defaults to `_` and the name.
	 * @param  bool              $public       Whether the type is public at all.
	 * @param  TypeUrls|false    $urls         URL settings, or `false` for no routes.
	 * @param  Listing           $listing      How the type's listing page lists entries.
	 * @param  TypeFeed|false    $feed         Feed settings, or `false` for no feed.
	 * @param  bool              $sitemap      Whether entries are in the sitemap.
	 * @param  DateArchives      $dateArchives How finely date archives go.
	 * @param  iterable<Field>   $fields       Fields beyond the built-in ones.
	 * @param  bool              $closed       Whether undeclared front matter is an error.
	 * @param  ?TypeLabels       $labels       Defaults to labels made from the name.
	 * @param  string            $description  What the type is for, in a sentence.
	 * @param  ?string           $icon         An icon name for the admin.
	 * @param  array<PeopleField>|bool $people  How entries credit people: `true` for `authors`, `false` for none.
	 * @param  bool              $llms         Whether entries are listed in `llms.txt` (D-398; each kind's default, `TypeKind::inLlmsByDefault()`).
	 * @param  ?FileName         $filename     How new files are named (D-511, D-514); the slug alone by default.
	 * @throws InvalidContentType
	 */
	protected function __construct(
		public string $name,
		?string $folder,
		public bool $public,
		public TypeUrls|false $urls,
		public Listing $listing,
		public TypeFeed|false $feed,
		public bool $sitemap,
		public DateArchives $dateArchives,
		iterable $fields,
		bool $closed,
		?TypeLabels $labels = null,
		string $description = '',
		?string $icon = null,
		array|bool $people = false,
		public bool $llms = true,
		public ?FileName $filename = null
	) {
		if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
			throw new InvalidContentType(sprintf(
				'Content type name "%s" must start with a lowercase letter and use only lowercase letters, digits, and underscores.',
				$name
			));
		}

		try {
			$this->schema = new Schema($fields, $closed);
		} catch (InvalidSchema $e) {
			throw new InvalidContentType(sprintf('Content type "%s" has invalid fields: %s', $name, $e->getMessage()), previous: $e);
		}

		$this->folder      = self::normalizeFolder($folder ?? self::defaultFolder($name), $name);
		$this->labels      = $labels ?? TypeLabels::named($name);
		$this->description = trim($description);
		$this->icon        = $icon === null || trim($icon) === '' ? null : trim($icon);
		$this->people      = self::peopleFields($people, $name);
	}

	/**
	 * Returns the type's kind.
	 */
	abstract public function kind(): TypeKind;

	/**
	 * Returns the URL prefix, without slashes: the URLs' prefix, or the
	 * folder without the `_` that starts its folder names (`_posts` is
	 * `posts`) when there isn't one. Types without URLs have none.
	 */
	public function prefix(): string
	{
		if ($this->urls === false) {
			return '';
		}

		return $this->urls->prefix ?? self::publicPath($this->folder);
	}

	/**
	 * Returns the path the page catch-all serves the type's entries
	 * under, without slashes: the folder without the `_` that starts its
	 * folder names (`_docs` is `docs`), as `prefix()` drops it (D-386).
	 */
	public function pagePath(): string
	{
		return self::publicPath($this->folder);
	}

	/**
	 * Returns the full route pattern for a route key, such as
	 * `/archives/{year}/{month}/{day}/{name}` for `single`, or `null` when
	 * the type has no URLs or no such key.
	 */
	public function routePattern(string $key): ?string
	{
		$path = $this->urls === false ? null : $this->urls->path($key) ?? $this->peoplePaths()[$key] ?? null;

		return $path === null ? null : '/' . trim($this->prefix() . '/' . $path, '/');
	}

	/**
	 * Returns whether the type has routes of its own.
	 */
	public function hasUrls(): bool
	{
		return $this->urls !== false;
	}

	/**
	 * Returns whether the type's entries credit people at all.
	 */
	public function credits(): bool
	{
		return $this->people !== [];
	}

	/**
	 * Returns one of the type's people fields, or `null`.
	 */
	public function peopleField(string $field): ?PeopleField
	{
		return $this->people[$field] ?? null;
	}

	/**
	 * Returns the people fields with archives (D-351): the type is public
	 * and has routes, and the field has an archive word. The site also
	 * needs a profiles type.
	 *
	 * @return array<string, PeopleField>
	 */
	public function archivedPeople(): array
	{
		return $this->public && $this->urls !== false
			? array_filter($this->people, static fn (PeopleField $field): bool => $field->hasArchive())
			: [];
	}

	/**
	 * Returns the type without the people fields that read any of these
	 * front matter keys, as a field or an alias. The loader uses it so a
	 * taxonomy's term field (1.x's `author` taxonomy, say) wins over a
	 * people field reading the same key (D-351).
	 */
	#[NoDiscard]
	public function withoutPeopleReading(string ...$keys): static
	{
		$people = array_filter($this->people, static fn (PeopleField $field): bool => array_intersect([$field->field, ...$field->aliases], $keys) === []);

		return count($people) === count($this->people) ? $this : clone($this, ['people' => $people]);
	}

	/**
	 * Returns the default paths of the people archives' route keys, which
	 * the URLs' `paths` can move.
	 *
	 * @return array<string, string>
	 */
	public function peoplePaths(): array
	{
		return array_merge([], ...array_values(array_map(static fn (PeopleField $field): array => $field->paths(), $this->archivedPeople())));
	}

	/**
	 * Returns whether the page catch-all serves the type's entries at
	 * their folder paths, as 1.x did for types without routes.
	 */
	public function servedAsPages(): bool
	{
		return ! $this->hasUrls();
	}

	/**
	 * Returns whether the type has a feed.
	 */
	public function hasFeed(): bool
	{
		return $this->feed !== false;
	}

	/**
	 * Returns the type the listing page lists: the listing's `type`, or the
	 * type itself.
	 */
	public function listedType(): string
	{
		return $this->listing->type ?? $this->name;
	}

	/**
	 * Returns the listing page's 1.x query arguments, for
	 * `Query::fromArray()`.
	 *
	 * @return array<string, mixed>
	 */
	public function listingArguments(): array
	{
		return [...$this->listing->arguments(), 'type' => $this->listedType()];
	}

	/**
	 * Returns the key of an entry's parent in this type, from its key and
	 * normalized front matter, or `null` when it has none. Only trees and
	 * hierarchical taxonomies nest.
	 *
	 * @param array<string, mixed> $values
	 */
	public function parentKey(string $key, array $values): ?string
	{
		return null;
	}

	/**
	 * Returns how the type names the files it creates: its own pattern
	 * (`filename`, any kind, D-511, D-514), else the slug alone (D-515).
	 */
	public function naming(): FileName
	{
		return $this->filename ?? FileName::byDefault();
	}

	/**
	 * Returns how the type's entries are ordered when nothing says
	 * otherwise, as `Query::orderBy()` takes it: newest published first
	 * here. Never by file, which a database doesn't have (D-516).
	 *
	 * @return array{string, Order}
	 */
	public function order(): array
	{
		return ['published', Order::Desc];
	}

	/**
	 * Returns the field other entries reference this type's entries
	 * through, or `null` for a type that isn't a taxonomy.
	 */
	public function termField(): ?ReferenceField
	{
		return null;
	}

	/**
	 * Returns whether other entries reference this type's entries as
	 * terms (a taxonomy's, or profiles), which the index keeps a reverse
	 * lookup for and which may be virtual.
	 */
	public function hasTerms(): bool
	{
		return $this->termField() !== null;
	}

	/**
	 * Builds a type from a definition array. Keys are the kind's
	 * constructor parameter names or the 1.x option names; `urls` may be
	 * `false` or a map (`TypeUrls::fromArray()`), `listing` a map
	 * (`Listing::fromArray()`), `feed` a boolean or a map
	 * (`TypeFeed::fromArray()`), `dateArchives` names a `DateArchives`,
	 * `filename` is a `FileName` pattern, and `fields` (with `closed`)
	 * defines the schema.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidContentType
	 */
	public static function fromArray(array $data, FieldFactory $fields): self
	{
		$name = $data['name'] ?? null;

		if (! is_string($name)) {
			throw new InvalidContentType('A content type definition needs a "name".');
		}

		$data    = self::renamed($data, $name);
		$kind    = self::kindOf($data, $name);
		$unknown = array_diff(array_map(strval(...), array_keys($data)), ['name', 'kind', ...$kind->options()]);

		if ($unknown !== []) {
			throw new InvalidContentType(sprintf('Content type "%s" (%s) has unknown options: %s.', $name, $kind->value, implode(', ', $unknown)));
		}

		$definition = new Definition($data, sprintf('Content type "%s"', $name));

		try {
			$schema = $fields->schema($definition->listOrMap('fields'), $definition->bool('closed'));
			$common = [
				'name'        => $name,
				'folder'      => $definition->nullableString('folder'),
				'public'      => $definition->bool('public', true),
				'sitemap'     => $definition->bool('sitemap', true),
				'fields'      => array_values($schema->fields),
				'closed'      => $schema->closed,
				'labels'      => TypeLabels::fromArray($definition->map('labels'), $name),
				'description' => $definition->nullableString('description') ?? '',
				'icon'        => $definition->nullableString('icon'),
				'filename'    => self::filename($definition, $name)
			];

			if ($kind === TypeKind::Profiles) {
				return new Profiles(...[
					...$common,
					'llms'    => $definition->bool('llms', false),
					'urls'    => self::urls($data['urls'] ?? [], $name),
					'listing' => Listing::fromArray($definition->map('listing'), sprintf('Content type "%s" listing', $name)),
					'feed'    => self::feed($data['feed'] ?? false, $name)
				]);
			}

			$common['people'] = array_key_exists('people', $data)
				? PeopleField::listFrom($data['people'], sprintf('Content type "%s"', $name))
				: $kind === TypeKind::Collection;

			if ($kind === TypeKind::Tree) {
				return new Tree(...[...$common, 'llms' => $definition->bool('llms', true)]);
			}

			$common = [
				...$common,
				'urls'    => self::urls($data['urls'] ?? [], $name),
				'listing' => Listing::fromArray($definition->map('listing'), sprintf('Content type "%s" listing', $name)),
				'feed'    => self::feed($data['feed'] ?? false, $name)
			];

			return match ($kind) {
				TypeKind::Collection => new Collection(...[...$common, 'dateArchives' => self::dateArchives($definition, $name), 'llms' => $definition->bool('llms', true)]),
				TypeKind::Taxonomy   => new Taxonomy(...[
					...$common,
					'types'       => $definition->strings('types'),
					'field'       => $definition->nullableString('field'),
					'aliases'     => $definition->strings('aliases'),
					'termListing'  => Listing::fromArray($definition->map('termListing'), sprintf('Content type "%s" termListing', $name)),
					'hierarchical' => $definition->bool('hierarchical'),
					'llms'         => $definition->bool('llms', false)
				])
			};
		} catch (InvalidSchema $e) {
			throw new InvalidContentType($e->getMessage(), previous: $e);
		}
	}

	/**
	 * Returns whether a data file may change the type when it's defined
	 * in code (D-349): collections, taxonomies, and trees in a folder
	 * (D-386). The site's pages and its profiles type stay as the code
	 * defines them.
	 */
	public function isOverridable(): bool
	{
		return true;
	}

	/**
	 * Returns what the type is to the site, for messages about a type
	 * that can't be overridden: "the site's pages", say.
	 */
	public function role(): string
	{
		return sprintf('a %s type', $this->kind()->value);
	}

	/**
	 * Returns the type with a data file's options laid over it (D-349):
	 * each option the data names replaces the type's whole option, by its
	 * 2.x or 1.x name. The name, kind, and folder stay the type's, since
	 * entries are filed by them. The site's pages and profiles types
	 * can't be overridden (`isOverridable()`).
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidContentType
	 */
	public function overriddenBy(array $data, FieldFactory $fields): self
	{
		if (! $this->isOverridable()) {
			throw new InvalidContentType(sprintf(
				'The "%s" content type is %s, which user/data/types can\'t change; define it in one place.',
				$this->name,
				$this->role()
			));
		}

		$data   = self::renamed($data, $this->name);
		$kind   = array_key_exists('kind', $data) || array_key_exists('taxonomy', $data) ? self::kindOf($data, $this->name) : $this->kind();
		$folder = $data['folder'] ?? $this->folder;

		unset($data['name'], $data['kind'], $data['folder']);

		if ($kind !== $this->kind() || ! is_string($folder) || trim($folder, '/') !== $this->folder) {
			throw new InvalidContentType(sprintf('user/data/types/%s can\'t change the type\'s kind or folder; entries are filed by them.', $this->name));
		}

		return self::fromArray([...$this->toArray(), ...$data], $fields);
	}

	/**
	 * Returns the type as a definition array that `fromArray()` accepts,
	 * leaving out settings at their defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		$data = [
			'name'        => $this->name,
			'kind'        => $this->kind()->value,
			'folder'      => $this->folder === self::defaultFolder($this->name) ? null : $this->folder,
			'urls'        => $this->urls === false ? false : ($this->urls->toArray() ?: null),
			'listing'     => $this->listing->toArray(),
			'feed'        => $this->feed === false ? null : ($this->feed->toArray() ?: true),
			'public'      => $this->public ? null : false,
			'sitemap'     => $this->sitemap ? null : false,
			'llms'        => $this->llms === $this->kind()->inLlmsByDefault() ? null : $this->llms,
			'labels'      => $this->labels->toArray($this->name),
			'description' => $this->description === '' ? null : $this->description,
			'icon'        => $this->icon,
			'filename'    => $this->filename?->pattern,
			...$this->options(),
			...$this->schema->toArray()
		];

		$data = array_intersect_key($data, array_flip(['name', 'kind', ...$this->kind()->options()]));

		return array_filter($data, static fn (mixed $value): bool => $value !== null && $value !== []);
	}

	/**
	 * Returns the kind's own settings for `toArray()`.
	 *
	 * @return array<string, mixed>
	 */
	protected function options(): array
	{
		return [];
	}

	/**
	 * Returns the type's people fields as the `people` option writes
	 * them: `false` for none, `true` for the `authors` field alone at its
	 * defaults, or a map of fields to their settings.
	 *
	 * @return array<string, array<string, mixed>|true>|bool
	 */
	public function peopleValue(): array|bool
	{
		return match (true) {
			$this->people === []                                                      => false,
			$this->people == [PeopleField::AUTHORS => PeopleField::authors()] => true,
			default                                                                   => array_map(static fn (PeopleField $field): array|bool => $field->toArray() ?: true, $this->people)
		};
	}

	/**
	 * Returns the `people` option for `toArray()`: `null` when it's the
	 * kind's default (`$byDefault`, the `authors` field), else
	 * `peopleValue()`.
	 *
	 * @return array<string, array<string, mixed>|true>|bool|null
	 */
	protected function peopleOption(bool $byDefault): array|bool|null
	{
		$value = $this->peopleValue();

		return $value === $byDefault ? null : $value;
	}

	/**
	 * Returns people fields keyed by field.
	 *
	 * @param  array<PeopleField>|bool $people
	 * @return array<string, PeopleField>
	 * @throws InvalidContentType
	 */
	private static function peopleFields(array|bool $people, string $name): array
	{
		if (is_bool($people)) {
			return $people ? [PeopleField::AUTHORS => PeopleField::authors()] : [];
		}

		$fields = [];

		foreach ($people as $field) {
			if (isset($fields[$field->field])) {
				throw new InvalidContentType(sprintf('Content type "%s" credits people through "%s" twice.', $name, $field->field));
			}

			$fields[$field->field] = $field;
		}

		return $fields;
	}

	/**
	 * Returns the folder a type has when it doesn't name one: `_` and its
	 * name, so type folders stand apart from the page folders beside them
	 * in the content root. The page type overrides it with the root.
	 */
	private static function defaultFolder(string $name): string
	{
		return "_{$name}";
	}

	/**
	 * Returns a folder path without the `_` that starts its folder names.
	 */
	private static function publicPath(string $folder): string
	{
		return implode('/', array_map(static fn (string $segment): string => ltrim($segment, '_'), explode('/', $folder)));
	}

	/**
	 * Moves 1.x option names to their 2.x names.
	 *
	 * @param  array<array-key, mixed> $data
	 * @return array<array-key, mixed>
	 * @throws InvalidContentType
	 */
	private static function renamed(array $data, string $name): array
	{
		$renames = [
			'path'            => 'folder',
			'routing'         => 'urls',
			'collection'      => 'listing',
			'field_aliases'   => 'aliases',
			'term_collection' => 'termListing'
		];

		foreach ($renames as $old => $new) {
			if (array_key_exists($old, $data)) {
				$data[$new] ??= $data[$old];
				unset($data[$old]);
			}
		}

		if (array_key_exists('authors', $data)) {
			if (array_key_exists('people', $data)) {
				throw new InvalidContentType(sprintf('Content type "%s" sets both "authors" and "people"; "authors" is short for the people field "authors".', $name));
			}

			$data['people'] = $data['authors'];
			unset($data['authors']);
		}

		if (array_key_exists('collect', $data)) {
			$collect = $data['collect'];
			unset($data['collect']);

			if (is_string($collect)) {
				$data['listing'] = [...(is_array($data['listing'] ?? null) ? $data['listing'] : []), 'type' => $collect];
			} elseif ($collect !== false && $collect !== null) {
				throw new InvalidContentType(sprintf('Content type "%s" "collect" must be a type name or false.', $name));
			}
		}

		if (array_key_exists('term_collect', $data)) {
			$data['types'] ??= $data['term_collect'];
			unset($data['term_collect']);
		}

		if (array_key_exists('date_archives', $data) || array_key_exists('time_archives', $data)) {
			$flags = new Definition($data, sprintf('Content type "%s"', $name));

			try {
				$data['dateArchives'] ??= DateArchives::fromFlags($flags->bool('date_archives'), $flags->bool('time_archives'))->value;
			} catch (InvalidSchema $e) {
				throw new InvalidContentType($e->getMessage(), previous: $e);
			}

			unset($data['date_archives'], $data['time_archives']);
		}

		return $data;
	}

	/**
	 * Returns the kind a definition names with `kind`, or with 1.x's
	 * `taxonomy` flag, and drops the flag.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidContentType
	 */
	private static function kindOf(array &$data, string $name): TypeKind
	{
		$taxonomy = $data['taxonomy'] ?? null;
		$kind     = $data['kind'] ?? null;
		unset($data['taxonomy']);

		if ($taxonomy !== null && ! is_bool($taxonomy)) {
			throw new InvalidContentType(sprintf('Content type "%s" "taxonomy" must be true or false.', $name));
		}

		if ($kind === null) {
			return $taxonomy === true ? TypeKind::Taxonomy : TypeKind::Collection;
		}

		$case = is_string($kind) ? TypeKind::tryFrom($kind) : null;

		if ($case === null) {
			throw new InvalidContentType(sprintf(
				'Content type "%s" "kind" must be one of %s.',
				$name,
				implode(', ', array_column(TypeKind::cases(), 'value'))
			));
		}

		if ($taxonomy !== null && $taxonomy !== ($case === TypeKind::Taxonomy)) {
			throw new InvalidContentType(sprintf('Content type "%s" sets "kind: %s" and "taxonomy: %s".', $name, $case->value, $taxonomy ? 'true' : 'false'));
		}

		return $case;
	}

	/**
	 * Reads the `urls` option.
	 *
	 * @throws InvalidSchema
	 */
	private static function urls(mixed $value, string $name): TypeUrls|false
	{
		if ($value === false) {
			return false;
		}

		if (! is_array($value)) {
			throw new InvalidSchema(sprintf('Content type "%s" "urls" must be false or a map.', $name));
		}

		return TypeUrls::fromArray($value, sprintf('Content type "%s" urls', $name));
	}

	/**
	 * Reads the `feed` option.
	 *
	 * @throws InvalidSchema
	 * @throws InvalidContentType
	 */
	private static function feed(mixed $value, string $name): TypeFeed|false
	{
		return match (true) {
			$value === false => false,
			$value === true  => new TypeFeed(),
			is_array($value) => TypeFeed::fromArray($value, sprintf('Content type "%s" feed', $name)),
			default          => throw new InvalidSchema(sprintf('Content type "%s" "feed" must be true, false, or a map.', $name))
		};
	}

	/**
	 * Reads the `dateArchives` option.
	 *
	 * @throws InvalidSchema
	 */
	private static function dateArchives(Definition $definition, string $name): DateArchives
	{
		return DateArchives::tryFrom($definition->string('dateArchives', DateArchives::None->value))
			?? throw new InvalidSchema(sprintf(
				'Content type "%s" "dateArchives" must be one of %s.',
				$name,
				implode(', ', array_column(DateArchives::cases(), 'value'))
			));
	}

	/**
	 * Reads the `filename` option.
	 *
	 * @throws InvalidSchema
	 * @throws InvalidContentType
	 */
	private static function filename(Definition $definition, string $name): ?FileName
	{
		$pattern = $definition->nullableString('filename');

		try {
			return $pattern === null || trim($pattern) === '' ? null : new FileName(trim($pattern));
		} catch (InvalidContentType $e) {
			throw new InvalidContentType(sprintf('Content type "%s": %s', $name, $e->getMessage()), previous: $e);
		}
	}

	/**
	 * Trims a type folder's slashes and rejects unsafe segments.
	 *
	 * @throws InvalidContentType
	 */
	private static function normalizeFolder(string $folder, string $name): string
	{
		$folder = trim(str_replace('\\', '/', $folder), '/');

		if ($folder !== '' && array_any(explode('/', $folder), static fn (string $segment): bool => in_array($segment, ['', '.', '..'], true))) {
			throw new InvalidContentType(sprintf('Content type "%s" has an invalid folder "%s".', $name, $folder));
		}

		return $folder;
	}
}
