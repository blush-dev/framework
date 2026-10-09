<?php

/**
 * Data type writer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use Closure;
use Blush\Container\Attributes\Defer;
use Blush\Content\ContentConfig;
use Blush\Content\Relation\Relation;
use Blush\Data\DataKeys;
use Blush\Field\FieldFactory;
use Blush\Field\InvalidSchema;
use Blush\Storage\Record\KeyedTable;
use Blush\Storage\Record\RecordException;

/**
 * Writes the types the site defines in data, `user/data/types/{name}`
 * (D-042, D-311), for the admin: creates one, changes the settings the
 * admin edits, and deletes one. It also changes a collection
 * or tree in a folder (D-386) defined in code (D-349), in a file of the same name that holds only
 * the options that differ from the code's, and resets one by deleting
 * that file.
 *
 * Changes are given by canonical option name (`labels`, `description`,
 * `icon`, `prefix` for the URL prefix (a tree's own `prefix`, D-683),
 * `paths` for route keys' paths (`null` or `''` for a key's default,
 * D-350), `public`, `sitemap`, `llms` (D-398), `feed`, `byline` (D-602),
 * `dateArchives`, `filename` (D-511), `folders` for the folder pattern
 * (`{year}`, D-629), `hierarchical`, `order` (D-593), and `fields`);
 * `null` removes one. A type is kept in `_` and its name (D-683), so no
 * folder is written. They're applied to the file's own
 * data (or, for a code type, to the type as the code and the file make
 * it) and the type is built from that (`ContentType::fromArray()`), so
 * it's checked as the loader checks it; each changed option is then
 * written as the type itself writes it (`ContentType::toArray()`), which
 * leaves out defaults, under the name the file already uses, replacing
 * any 1.x name for it. For a code type, an option back at the code's
 * value is removed from the file, and the file with it when it's empty.
 * Everything else in the file is left as the author wrote it: the
 * file keeps its other keys. Types are JSON files (D-490, D-631).
 *
 * Types are records in the `types` table (`DefinitionTables`, D-672).
 * Each change is checked against every other type before it's kept: in
 * a transaction, the record is written, all the types are loaded again
 * (`ContentTypeLoader`), and when they don't fit together (a relation
 * listing a type that's gone, say), the transaction
 * puts the record back as it was, so two writes can't interleave.
 */
final readonly class DataTypeWriter
{
	/**
	 * The options the admin edits, and the 1.x names each replaces.
	 */
	public const array OPTIONS = [
		'labels'       => [],
		'description'  => [],
		'icon'         => [],
		'urls'         => ['routing'],
		'public'       => [],
		'sitemap'      => [],
		'llms'         => [],
		'feed'         => [],
		'byline'       => [],
		'dateArchives' => ['date_archives', 'time_archives'],
		'filename'     => [],
		'folders'      => [],
		'prefix'       => [],
		'hierarchical' => [],
		'order'        => [],
		'fields'       => []
	];

	/**
	 * @param Closure(): ContentTypeLoader $loader
	 */
	public function __construct(
		private ContentConfig $config,
		private DefinitionTables $tables,
		private FieldFactory $fields,
		#[Defer(ContentTypeLoader::class)] private Closure $loader
	) {}

	/**
	 * Returns where a data type is kept (`user/data/types/post.json`), or
	 * `null` when the `types` table has none by its name.
	 *
	 * @throws InvalidContentType When the name isn't a type name.
	 */
	public function location(string $name): ?string
	{
		self::assertName($name);

		try {
			return $this->table()->has($name) ? $this->table()->location($name) : null;
		} catch (RecordException $error) {
			throw new InvalidContentType($error->getMessage(), previous: $error);
		}
	}

	/**
	 * Returns a data type's own options, as kept (with any 1.x names), or
	 * an empty array when it has none.
	 *
	 * @return array<array-key, mixed>
	 * @throws InvalidContentType When the name isn't a type name, or the record can't be read.
	 */
	public function data(string $name): array
	{
		self::assertName($name);

		try {
			return $this->table()->find($name) ?? [];
		} catch (RecordException $error) {
			throw new InvalidContentType(sprintf('%s Fix it by hand first.', $error->getMessage()), previous: $error);
		}
	}

	/**
	 * Creates a type in a new JSON file, and returns every type with it.
	 *
	 * @param  array<string, mixed> $changes
	 * @throws InvalidContentType When it can't be created or doesn't fit.
	 */
	public function create(string $name, TypeKind $kind, array $changes): ContentTypes
	{
		$this->assertEnabled();

		if ($kind === TypeKind::Profiles) {
			throw new InvalidContentType('The site has one profiles type; create a collection or a tree.');
		}

		if ($this->location($name) !== null) {
			throw new InvalidContentType(sprintf('user/data/types already defines "%s".', $name));
		}

		if ($this->codeType($name) !== null) {
			throw new InvalidContentType(sprintf('"%s" is already defined in code.', $name));
		}

		$base = $kind === TypeKind::Collection ? [] : ['kind' => $kind->value];

		return $this->write($name, $base, $changes, ['kind']);
	}

	/**
	 * Changes a data type's options, or a code type's through its data
	 * file (D-349), and returns every type with the change.
	 *
	 * @param  array<string, mixed> $changes
	 * @throws InvalidContentType When it can't be changed or doesn't fit.
	 */
	public function update(string $name, array $changes): ContentTypes
	{
		$this->assertEnabled();

		$code = $this->codeType($name);

		if ($this->location($name) === null && $code === null) {
			throw new InvalidContentType(sprintf('"%s" isn\'t defined in user/data/types, so it can\'t be changed here.', $name));
		}

		$data = $this->data($name);

		if (LegacyTaxonomy::is($data)) {
			throw new InvalidContentType(sprintf('"%s" is still written as a taxonomy; migrate it first, on Site Health or with content:taxonomies --write.', $name));
		}

		if (LegacyFolder::is($data)) {
			throw new InvalidContentType(sprintf('"%s" still names its folder; move it first, on Site Health or with content:type-folders --write.', $name));
		}

		return $this->write($name, $data, $changes, code: $code);
	}

	/**
	 * Puts a code type back as the code defines it, deleting the file
	 * that changes it (D-349), and returns every type.
	 *
	 * @throws InvalidContentType When it isn't a code type, or nothing changes it.
	 */
	public function reset(string $name): ContentTypes
	{
		$this->assertEnabled();

		if ($this->codeType($name) === null) {
			throw new InvalidContentType(sprintf('"%s" isn\'t defined in code, so there\'s nothing to reset it to.', $name));
		}

		if ($this->location($name) === null) {
			throw new InvalidContentType(sprintf('Nothing in user/data/types changes "%s".', $name));
		}

		return $this->checked(function () use ($name): void {
			$this->table()->delete($name);
		});
	}

	/**
	 * Returns the type the code defines under a name, when the admin may
	 * change it through a data file: an extension's
	 * collection, taxonomy, or tree in a folder. `null` for a name the code doesn't define
	 * (or only as a built-in type, which a data type replaces whole).
	 *
	 * @throws InvalidContentType When the code defines it as its pages or authors type.
	 */
	public function codeType(string $name): ?ContentType
	{
		[$types, $origins] = ($this->loader)()->codeTypes();

		$origin = $origins[$name] ?? null;

		if ($origin !== TypeOrigin::Extension) {
			return null;
		}

		$type = $types[$name];

		if (! $type->isOverridable()) {
			throw new InvalidContentType(sprintf('%s are %s, defined in code, so they can\'t be changed here.', $type->labels->plural, $type->role()));
		}

		return $type;
	}

	/**
	 * Returns whether the admin can write a type's fields: each is the
	 * class its type names in the field registry, so the file can name it
	 * by type. A code type's own field classes stay in code.
	 */
	public function fieldsEditable(ContentType $type): bool
	{
		foreach ($type->schema->fields as $field) {
			// Built again by type, it's the same field, down to a list's item.
			try {
				$rebuilt = $this->fields->fromArray(array_diff_key($field->toArray(), ['class' => true]));
			} catch (InvalidSchema) {
				return false;
			}

			if ($rebuilt->toArray() !== $field->toArray()) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Deletes a data type's file, and returns the types left. Its entries
	 * stay where they are.
	 *
	 * @throws InvalidContentType When it isn't a data type, or others need it.
	 */
	public function delete(string $name): ContentTypes
	{
		$this->assertEnabled();

		if ($this->codeType($name) !== null) {
			throw new InvalidContentType(sprintf('"%s" is defined in code, so it can\'t be deleted here; reset it to undo the changes made here.', $name));
		}

		if ($this->location($name) === null) {
			throw new InvalidContentType(sprintf('"%s" isn\'t defined in user/data/types, so it can\'t be deleted here.', $name));
		}

		// The relations that name it say why it can't go yet (D-593).
		$naming = array_filter(($this->loader)()->load()->relations(), static fn (Relation $relation): bool => in_array($name, [...$relation->from, ...$relation->to], true));

		if ($naming !== []) {
			throw new InvalidContentType(sprintf(
				'The %s %s %s; remove %s first.',
				implode(' and ', array_map(static fn (Relation $relation): string => "\"{$relation->name}\"", $naming)),
				count($naming) === 1 ? 'relation names' : 'relations name',
				$name,
				count($naming) === 1 ? 'it' : 'them'
			));
		}

		return $this->checked(function () use ($name): void {
			$this->table()->delete($name);
		});
	}

	/**
	 * Applies changes to a type's data, checks the type, and writes the
	 * changed options.
	 *
	 * @param  array<array-key, mixed> $data    The record's data.
	 * @param  array<string, mixed>    $changes
	 * @param  list<string>            $also    More keys to write as they are (a new type's `kind`).
	 * @param  ?ContentType             $code    The type the code defines, when the file changes it.
	 * @throws InvalidContentType
	 */
	private function write(string $name, array $data, array $changes, array $also = [], ?ContentType $code = null): ContentTypes
	{
		$merged  = $code === null ? $data : $code->overriddenBy($data, $this->fields)->toArray();
		$written = [];
		$paths   = [];
		$tree    = ($code?->kind() ?? TypeKind::tryFrom(is_string($merged['kind'] ?? null) ? $merged['kind'] : '')) === TypeKind::Tree;

		if ($code !== null && array_key_exists('fields', $changes) && ! $this->fieldsEditable($code)) {
			throw new InvalidContentType('Its fields include field classes from code, so they\'re changed in code.');
		}

		foreach ($changes as $key => $value) {
			if ($key === 'folders' && $value !== null && ! is_string($value)) {
				throw new InvalidContentType('"folders" must be a folder pattern, such as {year}, or null.');
			}

			if ($key === 'prefix' && $tree) {
				$value = is_string($value) ? trim($value, '/ ') : null;
			} elseif ($key === 'prefix') {
				$key   = 'urls';
				$value = $this->urls($merged, $value);
			} elseif ($key === 'paths') {
				$key   = 'urls';
				$paths = is_array($value) ? array_keys($value) : [];
				$value = $this->routePaths($name, $merged, $value);
			} elseif ($key === 'labels') {
				$value = $this->labels($merged, $value);
			} elseif ($key === 'feed' && $value === true && is_array($merged['feed'] ?? null)) {
				$value = $merged['feed'];
			}

			if (! array_key_exists($key, self::OPTIONS)) {
				throw new InvalidContentType(sprintf('"%s" isn\'t an option the admin changes.', $key));
			}

			foreach (self::OPTIONS[$key] as $old) {
				unset($merged[$old]);
			}

			if ($value === null || $value === '' || $value === []) {
				unset($merged[$key]);
			} else {
				$merged[$key] = $value;
			}

			$written[] = $key;
		}

		$type      = ContentType::fromArray(['name' => $name, ...$merged], $this->fields);
		$canonical = self::canonical($type);
		$original  = $code === null ? [] : self::canonical($code);

		if ($paths !== []) {
			$this->checkPaths($type, $paths);
		}

		$sets = [];

		foreach ([...$also, ...$written] as $key) {
			$value = $canonical[$key] ?? null;

			// Over code, an option at the code's value isn't written, and
			// one at its default while the code's isn't says so.
			if ($code !== null) {
				$value = $value === ($original[$key] ?? null) ? null : ($value ?? self::explicit($type, $key));
			}

			$sets[$key] = $value;
		}

		// A collection is the kind a type is without one.
		if (($sets['kind'] ?? null) === TypeKind::Collection->value) {
			$sets['kind'] = null;
		}

		// What's left in the file, to remove a code type's empty one.
		$left = $data;

		foreach ($sets as $key => $value) {
			foreach (self::OPTIONS[$key] ?? [] as $old) {
				unset($left[$old]);
			}

			if ($value === null) {
				unset($left[$key]);
			} else {
				$left[$key] = $value;
			}
		}

		return $this->checked(function () use ($name, $sets, $code, $left): void {
			$table = $this->table();

			if ($code !== null && $left === []) {
				$table->delete($name);

				return;
			}

			$table->save($name, DataKeys::apply($table->find($name) ?? [], $sets, self::OPTIONS));
		});
	}

	/**
	 * A type's options as the file writes them: no field classes, and no
	 * prefix that's its name, which it has without one (D-683).
	 *
	 * @return array<string, mixed>
	 */
	private static function canonical(ContentType $type): array
	{
		$canonical = self::withoutClasses($type->toArray());
		$urls      = $canonical['urls'] ?? null;

		if (is_array($urls) && ($urls['prefix'] ?? null) === $type->name) {
			unset($urls['prefix']);
			$canonical['urls'] = $urls === [] ? null : $urls;
		}

		if (($canonical['prefix'] ?? null) === $type->name) {
			unset($canonical['prefix']);
		}

		return $canonical;
	}

	/**
	 * An option's value written out when it's at its default, which the
	 * type itself leaves out, so it can stand over a code type's other
	 * value.
	 */
	private static function explicit(ContentType $type, string $key): mixed
	{
		return match ($key) {
			'public'       => $type->public,
			'sitemap'      => $type->sitemap,
			'llms'         => $type->llms,
			'feed'         => $type->hasFeed(),
			'byline'       => null,
			'description'  => '',
			'icon'         => '',
			'dateArchives' => $type->dateArchives->value,
			'filename'     => $type->naming()->pattern,
			'folders'      => '',
			'prefix'       => $type->name,
			'hierarchical' => false,
			'order'        => TypeOrder::Published->value,
			default        => []
		};
	}

	/**
	 * Checks the paths a change set, now that the type has them.
	 *
	 * @param  list<array-key> $keys
	 * @throws InvalidContentType
	 */
	private function checkPaths(ContentType $type, array $keys): void
	{
		if ($type->urls === false) {
			return;
		}

		$types      = ($this->loader)()->load();
		$taxonomies = array_keys($types->termTypes());
		$relations  = array_keys($types->relationArchives($type));

		foreach ($keys as $key) {
			$key  = (string) $key;
			$path = $type->urls->path($key);

			if ($path !== null) {
				TypeRouteKeys::check($type, $key, $path, $taxonomies, "\"{$key}\"", $relations);
			}
		}
	}

	/**
	 * Runs a write in a transaction and loads every type again, which
	 * puts the record back when they don't fit together.
	 *
	 * @param  Closure(): void $write
	 * @throws InvalidContentType
	 */
	private function checked(Closure $write): ContentTypes
	{
		try {
			return $this->table()->transaction(function () use ($write): ContentTypes {
				$write();

				return ($this->loader)()->load();
			});
		} catch (RecordException $error) {
			throw new InvalidContentType($error->getMessage(), previous: $error);
		}
	}

	/**
	 * The `urls` a prefix change leaves: the file's own, with the prefix
	 * set or removed.
	 *
	 * @param  array<array-key, mixed> $data
	 * @return array<array-key, mixed>|null
	 * @throws InvalidContentType When the type has no URLs.
	 */
	private function urls(array $data, mixed $prefix): ?array
	{
		$urls = $data['urls'] ?? $data['routing'] ?? [];

		if ($urls === false) {
			throw new InvalidContentType('The type has no URLs of its own, so it has no prefix to change.');
		}

		$urls = is_array($urls) ? $urls : [];

		if (! is_string($prefix) || trim($prefix, '/ ') === '') {
			unset($urls['prefix']);
		} else {
			$urls['prefix'] = trim($prefix, '/ ');
		}

		return $urls === [] ? null : $urls;
	}

	/**
	 * The `urls` a change to route keys' paths leaves (D-350): the file's
	 * own, with each given key's path set, or back to its default for
	 * `null` or `''`. The `single` and `collection` shortcuts move into
	 * `paths`, which they'd otherwise win over.
	 *
	 * @param  array<array-key, mixed> $data
	 * @return array<array-key, mixed>|null
	 * @throws InvalidContentType When the type has no URLs, or a key isn't one.
	 */
	private function routePaths(string $name, array $data, mixed $paths): ?array
	{
		$urls = $data['urls'] ?? $data['routing'] ?? [];

		if ($urls === false) {
			throw new InvalidContentType('The type has no URLs of its own, so it has no paths to change.');
		}

		if (! is_array($paths) || ($paths !== [] && array_is_list($paths))) {
			throw new InvalidContentType('"paths" must map route keys to paths.');
		}

		$urls  = is_array($urls) ? $urls : [];
		$own   = is_array($urls['paths'] ?? null) ? $urls['paths'] : [];
		$types = ($this->loader)()->load();
		$type  = $types->find($name);
		$known = [
			...array_keys(TypeUrls::DEFAULT_PATHS),
			...($type === null ? [] : array_keys($types->relationPaths($type)))
		];

		foreach (['single', 'collection'] as $shortcut) {
			if (is_string($urls[$shortcut] ?? null)) {
				$own[$shortcut] = $urls[$shortcut];
			}

			unset($urls[$shortcut]);
		}

		foreach ($paths as $key => $path) {
			if (! in_array($key, $known, true)) {
				throw new InvalidContentType(sprintf('"%s" isn\'t a route key.', $key));
			}

			if ($path !== null && ! is_string($path)) {
				throw new InvalidContentType(sprintf('The "%s" path must be text.', $key));
			}

			if ($path === null || trim($path, '/ ') === '') {
				unset($own[$key]);
			} else {
				$own[$key] = trim($path, '/ ');
			}
		}

		if ($own === []) {
			unset($urls['paths']);
		} else {
			$urls['paths'] = $own;
		}

		return $urls === [] ? null : $urls;
	}



	/**
	 * The `labels` a change leaves: the file's own, with the given ones
	 * set and the empty ones removed.
	 *
	 * @param  array<array-key, mixed> $data
	 * @return array<array-key, mixed>|null
	 * @throws InvalidContentType
	 */
	private function labels(array $data, mixed $labels): ?array
	{
		if ($labels !== null && ! is_array($labels)) {
			throw new InvalidContentType('"labels" must map label names to text.');
		}

		$merged = [...(is_array($data['labels'] ?? null) ? $data['labels'] : []), ...($labels ?? [])];
		$merged = array_filter($merged, static fn (mixed $label): bool => ! (is_string($label) && trim($label) === '') && $label !== null);

		return $merged === [] ? null : $merged;
	}

	/**
	 * The definition without field classes: the field registry knows them
	 * by type, so a site's file never names PHP classes.
	 *
	 * @param  array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private static function withoutClasses(array $data): array
	{
		$strip = static function (mixed $field) use (&$strip): mixed {
			if (! is_array($field)) {
				return $field;
			}

			unset($field['class']);

			if (isset($field['item'])) {
				$field['item'] = $strip($field['item']);
			}

			if (isset($field['fields']) && is_array($field['fields'])) {
				$field['fields'] = array_map($strip, $field['fields']);
			}

			return $field;
		};

		if (isset($data['fields']) && is_array($data['fields'])) {
			$data['fields'] = array_map($strip, $data['fields']);
		}

		return $data;
	}

	/**
	 * The types' table.
	 */
	private function table(): KeyedTable
	{
		return $this->tables->types();
	}

	/**
	 * @throws InvalidContentType
	 */
	private function assertEnabled(): void
	{
		if (! $this->config->dataTypes) {
			throw new InvalidContentType('ContentConfig "dataTypes" is off, so types in user/data/types aren\'t read.');
		}
	}

	/**
	 * @throws InvalidContentType
	 */
	private static function assertName(string $name): void
	{
		if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
			throw new InvalidContentType(sprintf('"%s" isn\'t a type name: start with a lowercase letter and use lowercase letters, digits, and underscores.', $name));
		}
	}
}
