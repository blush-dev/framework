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

use Blush\Content\Schema\Definition;
use Blush\Content\Schema\Field;
use Blush\Content\Schema\FieldFactory;
use Blush\Content\Schema\Fields\ReferenceField;
use Blush\Content\Schema\InvalidSchema;
use Blush\Content\Schema\Schema;

/**
 * A content type: the entries in one folder of `user/content`, how they're
 * routed, listed, and fed, and the fields they have. The kinds are final
 * classes (D-157): `Collection` for listed entries such as posts,
 * `Taxonomy` for terms that group other entries, and `Pages` for the
 * built-in type that claims the content root. One model serves types
 * from code and from data (D-042).
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
	 * The type's name for people, for a group of its entries ("Literary
	 * genres"), such as the admin's navigation.
	 */
	public string $label;

	/**
	 * The type's name for people, for one entry ("Literary genre"), such
	 * as the admin's "New literary genre".
	 */
	public string $singular;

	/**
	 * @param  string            $name         Lowercase letters, digits, and underscores.
	 * @param  ?string           $folder       Defaults to the name.
	 * @param  bool              $public       Whether the type is public at all.
	 * @param  TypeUrls|false    $urls         URL settings, or `false` for no routes.
	 * @param  Listing           $listing      How the type's listing page lists entries.
	 * @param  TypeFeed|false    $feed         Feed settings, or `false` for no feed.
	 * @param  bool              $sitemap      Whether entries are in the sitemap.
	 * @param  DateArchives      $dateArchives How finely date archives go.
	 * @param  iterable<Field>   $fields       Fields beyond the built-in ones.
	 * @param  bool              $closed       Whether undeclared front matter is an error.
	 * @param  ?string           $label        Defaults to the singular made plural.
	 * @param  ?string           $singular     Defaults to the name made readable.
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
		?string $label = null,
		?string $singular = null
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

		$this->folder   = self::normalizeFolder($folder ?? $name, $name);
		$this->singular = $singular ?? self::readable($name);
		$this->label    = $label ?? self::plural($this->singular);
	}

	/**
	 * Returns the type's kind.
	 */
	abstract public function kind(): TypeKind;

	/**
	 * Returns the URL prefix, without slashes: the URLs' prefix, or the
	 * folder when there isn't one. Types without URLs have none.
	 */
	public function prefix(): string
	{
		return $this->urls === false ? '' : ($this->urls->prefix ?? $this->folder);
	}

	/**
	 * Returns the full route pattern for a route key, such as
	 * `/archives/{year}/{month}/{day}/{name}` for `single`, or `null` when
	 * the type has no URLs or no such key.
	 */
	public function routePattern(string $key): ?string
	{
		$path = $this->urls === false ? null : $this->urls->path($key);

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
	 * Returns the field other entries reference this type's entries
	 * through, or `null` for a type that isn't a taxonomy.
	 */
	public function termField(): ?ReferenceField
	{
		return null;
	}

	/**
	 * Builds a type from a definition array. Keys are the kind's
	 * constructor parameter names or the 1.x option names; `urls` may be
	 * `false` or a map (`TypeUrls::fromArray()`), `listing` a map
	 * (`Listing::fromArray()`), `feed` a boolean or a map
	 * (`TypeFeed::fromArray()`), `dateArchives` names a `DateArchives`,
	 * and `fields` (with `closed`) defines the schema.
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
			$schema = $fields->schema($definition->maps('fields'), $definition->bool('closed'));
			$common = [
				'name'    => $name,
				'folder'  => $definition->nullableString('folder'),
				'public'  => $definition->bool('public', true),
				'sitemap' => $definition->bool('sitemap', true),
				'fields'   => array_values($schema->fields),
				'closed'   => $schema->closed,
				'label'    => $definition->nullableString('label'),
				'singular' => $definition->nullableString('singular')
			];

			if ($kind === TypeKind::Pages) {
				return new Pages(...[...$common, 'folder' => $common['folder'] ?? '']);
			}

			$common = [
				...$common,
				'urls'    => self::urls($data['urls'] ?? [], $name),
				'listing' => Listing::fromArray($definition->map('listing'), sprintf('Content type "%s" listing', $name)),
				'feed'    => self::feed($data['feed'] ?? false, $name)
			];

			return match ($kind) {
				TypeKind::Collection => new Collection(...[...$common, 'dateArchives' => self::dateArchives($definition, $name)]),
				TypeKind::Taxonomy   => new Taxonomy(...[
					...$common,
					'types'       => $definition->strings('types'),
					'field'       => $definition->nullableString('field'),
					'aliases'     => $definition->strings('aliases'),
					'termListing' => Listing::fromArray($definition->map('termListing'), sprintf('Content type "%s" termListing', $name))
				])
			};
		} catch (InvalidSchema $e) {
			throw new InvalidContentType($e->getMessage(), previous: $e);
		}
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
			'name'    => $this->name,
			'kind'    => $this->kind()->value,
			'folder'  => $this->folder === $this->name ? null : $this->folder,
			'urls'    => $this->urls === false ? false : ($this->urls->toArray() ?: null),
			'listing' => $this->listing->toArray(),
			'feed'    => $this->feed === false ? null : ($this->feed->toArray() ?: true),
			'public'  => $this->public ? null : false,
			'sitemap' => $this->sitemap ? null : false,
			'label'    => $this->label === self::plural($this->singular) ? null : $this->label,
			'singular' => $this->singular === self::readable($this->name) ? null : $this->singular,
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
	 * Returns a type name made readable: `literary_form` becomes
	 * "Literary form".
	 */
	private static function readable(string $name): string
	{
		return ucfirst(str_replace('_', ' ', $name));
	}

	/**
	 * Returns an English plural for the default label: "Category" becomes
	 * "Categories", "Class" "Classes", "Post" "Posts". Types whose names
	 * don't follow these rules, or aren't English, set `label`.
	 */
	private static function plural(string $singular): string
	{
		return match (true) {
			preg_match('/[^aeiou]y$/i', $singular) === 1     => substr($singular, 0, -1) . 'ies',
			preg_match('/(s|x|z|ch|sh)$/i', $singular) === 1 => "{$singular}es",
			default                                           => "{$singular}s"
		};
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
