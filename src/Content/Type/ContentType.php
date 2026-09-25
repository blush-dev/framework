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
use Blush\Content\Schema\FieldFactory;
use Blush\Content\Schema\Fields\ReferenceField;
use Blush\Content\Schema\InvalidSchema;
use Blush\Content\Schema\Schema;

/**
 * A content type: the entries in one folder of `user/content`, how they're
 * routed, listed, and fed, and the fields they have. One model serves
 * types from code and from data (D-042):
 *
 *     new ContentType(
 *         'post',
 *         path: '_posts',
 *         routing: new TypeRouting('archives', ['single' => '{year}/{month}/{day}/{name}']),
 *         collection: ['order' => 'desc'],
 *         feed: new TypeFeed(taxonomy: 'category'),
 *         archives: ArchiveGranularity::Day
 *     );
 *
 * `fromArray()` also accepts every 1.x option name (D-078), such as
 * `date_archives` and `term_collect`.
 *
 * A **taxonomy**'s entries are terms that other entries reference through
 * the taxonomy's field (its name by default, or `$field` and its
 * aliases). Terms collect the entries of `$termCollect`, or of every type
 * when that's unset.
 */
final readonly class ContentType
{
	/**
	 * The folder under `user/content`, without slashes. The page type's
	 * is `''`, the content root.
	 */
	public string $path;

	/**
	 * The front matter key entries use to reference this taxonomy's terms.
	 */
	public string $field;

	/**
	 * The type whose entries this type's collection lists, or `false` for
	 * none. Defaults to the type itself.
	 */
	public string|false $collect;

	/**
	 * @param  string               $name           Lowercase letters, digits, and underscores.
	 * @param  ?string              $path           Defaults to the name.
	 * @param  bool                 $public         Whether the type is public at all.
	 * @param  TypeRouting|false    $routing        URL settings, or `false` for no routes.
	 * @param  array<string, mixed> $collection     Query arguments for the collection.
	 * @param  bool                 $taxonomy       Whether entries are terms.
	 * @param  ?string              $field          A taxonomy's term field; defaults to the name.
	 * @param  list<string>         $fieldAliases   Other keys the term field is read from.
	 * @param  string|false|null    $collect        See `$collect`.
	 * @param  ?string              $termCollect    The type a term's archive lists.
	 * @param  array<string, mixed> $termCollection Query arguments for term archives.
	 * @param  TypeFeed|false       $feed           Feed settings, or `false` for no feed.
	 * @param  bool                 $sitemap        Whether entries are in the sitemap.
	 * @param  ArchiveGranularity   $archives       How finely date archives go.
	 * @param  Schema               $schema         Fields beyond the built-in ones.
	 * @throws InvalidContentType
	 */
	public function __construct(
		public string $name,
		?string $path = null,
		public bool $public = true,
		public TypeRouting|false $routing = new TypeRouting(),
		public array $collection = [],
		public bool $taxonomy = false,
		?string $field = null,
		public array $fieldAliases = [],
		string|false|null $collect = null,
		public ?string $termCollect = null,
		public array $termCollection = [],
		public TypeFeed|false $feed = false,
		public bool $sitemap = true,
		public ArchiveGranularity $archives = ArchiveGranularity::None,
		public Schema $schema = new Schema()
	) {
		if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
			throw new InvalidContentType(sprintf(
				'Content type name "%s" must start with a lowercase letter and use only lowercase letters, digits, and underscores.',
				$name
			));
		}

		$this->path    = self::normalizePath($path ?? $name, $name);
		$this->field   = $field ?? $name;
		$this->collect = $collect ?? $name;
	}

	/**
	 * Returns the URL prefix, without slashes: the routing prefix, or the
	 * path when there isn't one. Types without routing have none.
	 */
	public function prefix(): string
	{
		return $this->routing === false ? '' : ($this->routing->prefix ?? $this->path);
	}

	/**
	 * Returns whether the type has routes of its own.
	 */
	public function hasRouting(): bool
	{
		return $this->routing !== false;
	}

	/**
	 * Returns whether the type has a feed.
	 */
	public function hasFeed(): bool
	{
		return $this->feed !== false;
	}

	/**
	 * Returns the field other entries reference this taxonomy's terms
	 * through, or `null` for a type that isn't a taxonomy.
	 */
	public function termField(): ?ReferenceField
	{
		return $this->taxonomy
			? new ReferenceField($this->field, $this->name)->aliases(...$this->fieldAliases)
			: null;
	}

	/**
	 * Builds a type from a definition array. Keys are the constructor's
	 * parameter names or the 1.x option names; `routing` may be `false` or
	 * a map of `prefix` and `paths`, `feed` may be a boolean or a map of
	 * `taxonomy` and `collection`, `archives` names an `ArchiveGranularity`,
	 * and `fields` (with `closed`) defines the schema.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidContentType
	 */
	public static function fromArray(array $data, FieldFactory $fields): self
	{
		$data = self::renamed($data);
		$name = $data['name'] ?? null;

		if (! is_string($name)) {
			throw new InvalidContentType('A content type definition needs a "name".');
		}

		$unknown = array_diff(array_keys($data), [
			'name', 'path', 'public', 'routing', 'collection', 'taxonomy', 'field', 'fieldAliases', 'collect',
			'termCollect', 'termCollection', 'feed', 'sitemap', 'archives', 'dateArchives', 'timeArchives',
			'fields', 'closed'
		]);

		if ($unknown !== []) {
			throw new InvalidContentType(sprintf('Content type "%s" has unknown options: %s.', $name, implode(', ', $unknown)));
		}

		$definition = new Definition($data, sprintf('Content type "%s"', $name));

		try {
			$collect = $data['collect'] ?? null;

			if ($collect !== null && $collect !== false && ! is_string($collect)) {
				throw new InvalidSchema(sprintf('Content type "%s" "collect" must be a type name or false.', $name));
			}

			return new self(
				name: $name,
				path: $definition->nullableString('path'),
				public: $definition->bool('public', true),
				routing: self::routing($data['routing'] ?? [], $name),
				collection: self::stringMap($definition->map('collection')),
				taxonomy: $definition->bool('taxonomy'),
				field: $definition->nullableString('field'),
				fieldAliases: $definition->strings('fieldAliases'),
				collect: $collect,
				termCollect: $definition->nullableString('termCollect'),
				termCollection: self::stringMap($definition->map('termCollection')),
				feed: self::feed($data['feed'] ?? false, $name),
				sitemap: $definition->bool('sitemap', true),
				archives: self::archives($definition, $name),
				schema: $fields->schema($definition->maps('fields'), $definition->bool('closed'))
			);
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
			'name'           => $this->name,
			'path'           => $this->path === $this->name ? null : $this->path,
			'public'         => $this->public ? null : false,
			'routing'        => $this->routing === false ? false : ($this->routing->toArray() ?: null),
			'collection'     => $this->collection,
			'taxonomy'       => $this->taxonomy ?: null,
			'field'          => $this->field === $this->name ? null : $this->field,
			'fieldAliases'   => $this->fieldAliases,
			'collect'        => $this->collect === $this->name ? null : $this->collect,
			'termCollect'    => $this->termCollect,
			'termCollection' => $this->termCollection,
			'feed'           => $this->feed === false ? null : ($this->feed->toArray() ?: true),
			'sitemap'        => $this->sitemap ? null : false,
			'archives'       => $this->archives === ArchiveGranularity::None ? null : $this->archives->value,
			...$this->schema->toArray()
		];

		return array_filter($data, static fn (mixed $value): bool => $value !== null && $value !== []);
	}

	/**
	 * Moves 1.x snake_case option names to their 2.x names.
	 *
	 * @param  array<array-key, mixed> $data
	 * @return array<array-key, mixed>
	 */
	private static function renamed(array $data): array
	{
		$renames = [
			'field_aliases'   => 'fieldAliases',
			'term_collect'    => 'termCollect',
			'term_collection' => 'termCollection',
			'date_archives'   => 'dateArchives',
			'time_archives'   => 'timeArchives'
		];

		foreach ($renames as $old => $new) {
			if (array_key_exists($old, $data)) {
				$data[$new] ??= $data[$old];
				unset($data[$old]);
			}
		}

		return $data;
	}

	/**
	 * Reads the `routing` option.
	 *
	 * @throws InvalidSchema
	 */
	private static function routing(mixed $value, string $name): TypeRouting|false
	{
		if ($value === false) {
			return false;
		}

		if (! is_array($value)) {
			throw new InvalidSchema(sprintf('Content type "%s" "routing" must be false or a map.', $name));
		}

		$routing = new Definition($value, sprintf('Content type "%s" routing', $name));
		$paths   = $routing->map('paths');

		if (! array_all($paths, static fn (mixed $path, mixed $key): bool => is_string($key) && is_string($path))) {
			throw new InvalidSchema(sprintf('Content type "%s" routing "paths" must map route keys to paths.', $name));
		}

		/** @var array<string, string> $paths */
		return new TypeRouting($routing->nullableString('prefix'), $paths);
	}

	/**
	 * Reads the `feed` option.
	 *
	 * @throws InvalidSchema
	 */
	private static function feed(mixed $value, string $name): TypeFeed|false
	{
		if ($value === false) {
			return false;
		}

		if ($value === true) {
			return new TypeFeed();
		}

		if (! is_array($value)) {
			throw new InvalidSchema(sprintf('Content type "%s" "feed" must be true, false, or a map.', $name));
		}

		$feed = new Definition($value, sprintf('Content type "%s" feed', $name));

		return new TypeFeed($feed->nullableString('taxonomy'), self::stringMap($feed->map('collection')));
	}

	/**
	 * Reads the `archives` option, or the 1.x flags.
	 *
	 * @throws InvalidSchema
	 */
	private static function archives(Definition $definition, string $name): ArchiveGranularity
	{
		if (! $definition->has('archives')) {
			return ArchiveGranularity::fromFlags($definition->bool('dateArchives'), $definition->bool('timeArchives'));
		}

		return ArchiveGranularity::tryFrom($definition->string('archives'))
			?? throw new InvalidSchema(sprintf(
				'Content type "%s" "archives" must be one of %s.',
				$name,
				implode(', ', array_column(ArchiveGranularity::cases(), 'value'))
			));
	}

	/**
	 * Checks that a map is keyed by strings.
	 *
	 * @param  array<array-key, mixed> $map
	 * @return array<string, mixed>
	 * @throws InvalidSchema
	 */
	private static function stringMap(array $map): array
	{
		if (! array_all($map, static fn (mixed $value, mixed $key): bool => is_string($key))) {
			throw new InvalidSchema('Query arguments must be keyed by name.');
		}

		/** @var array<string, mixed> $map */
		return $map;
	}

	/**
	 * Trims a type path's slashes and rejects unsafe segments.
	 *
	 * @throws InvalidContentType
	 */
	private static function normalizePath(string $path, string $name): string
	{
		$path = trim(str_replace('\\', '/', $path), '/');

		if ($path !== '' && array_any(explode('/', $path), static fn (string $segment): bool => in_array($segment, ['', '.', '..'], true))) {
			throw new InvalidContentType(sprintf('Content type "%s" has an invalid path "%s".', $name, $path));
		}

		return $path;
	}
}
