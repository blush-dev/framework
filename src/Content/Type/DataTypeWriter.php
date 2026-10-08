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
use Throwable;
use Blush\Container\Attributes\Defer;
use Blush\Content\ContentConfig;
use Blush\Content\Relation\Relation;
use Blush\Content\Writer\DataFileKeys;
use Blush\Core\Paths;
use Blush\Data\DataException;
use Blush\Data\DataLoader;
use Blush\Data\InvalidData;
use Blush\Field\FieldFactory;
use Blush\Field\InvalidSchema;
use Blush\Support\Filesystem;

/**
 * Writes the types the site defines in data, `user/data/types/{name}`
 * (D-042, D-311), for the admin: creates one, changes the settings the
 * admin edits, and deletes one. It also changes a collection
 * or tree in a folder (D-386) defined in code (D-349), in a file of the same name that holds only
 * the options that differ from the code's, and resets one by deleting
 * that file.
 *
 * Changes are given by canonical option name (`labels`, `description`,
 * `icon`, `prefix` for the URL prefix, `paths` for route keys' paths
 * (`null` or `''` for a key's default, D-350), `public`, `sitemap`,
 * `llms` (D-398),
 * `feed`, `byline` (D-602), `dateArchives`, `filename` (D-511), `folders` for the folder pattern
 * after the type's folder (`{year}`, written into `folder`, D-629), `hierarchical`, `order` (D-593), and
 * `fields`); `null` removes one. The folder itself never changes here. They're applied to the file's own
 * data (or, for a code type, to the type as the code and the file make
 * it) and the type is built from that (`ContentType::fromArray()`), so
 * it's checked as the loader checks it; each changed option is then
 * written as the type itself writes it (`ContentType::toArray()`), which
 * leaves out defaults, under the name the file already uses, replacing
 * any 1.x name for it. For a code type, an option back at the code's
 * value is removed from the file, and the file with it when it's empty.
 * Everything else in the file is left as the author wrote it: a JSON
 * file keeps its other keys, and a YAML file its other lines, comments
 * included. A new type is a JSON file (D-490).
 *
 * Each change is checked against every other type before it's kept: the
 * file is written, all the types are loaded again (`ContentTypeLoader`),
 * and when they don't fit together (two types in one folder, a relation
 * listing a type that's gone), the file is put back as it was. Writes
 * take a lock, so two can't interleave.
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
		'folder'       => ['path'],
		'hierarchical' => [],
		'order'        => [],
		'fields'       => []
	];

	/**
	 * @param Closure(): ContentTypeLoader $loader
	 */
	public function __construct(
		private Paths $paths,
		private ContentConfig $config,
		private DataLoader $data,
		private FieldFactory $fields,
		private Filesystem $filesystem,
		#[Defer(ContentTypeLoader::class)] private Closure $loader
	) {}

	/**
	 * Returns a data type's file, or `null` when it has none.
	 *
	 * @throws InvalidContentType When the name isn't a type name.
	 */
	public function path(string $name): ?string
	{
		self::assertName($name);

		try {
			return $this->data->find($this->directory(), $name);
		} catch (DataException $error) {
			throw new InvalidContentType($error->getMessage(), previous: $error);
		}
	}

	/**
	 * Creates a type in a new JSON file, and returns every type with it.
	 *
	 * @param  array<string, mixed> $changes
	 * @throws InvalidContentType When it can't be created or doesn't fit.
	 */
	public function create(string $name, TypeKind $kind, ?string $folder, array $changes): ContentTypes
	{
		$this->assertEnabled();

		if ($kind === TypeKind::Profiles) {
			throw new InvalidContentType('The site has one profiles type; create a collection or a tree.');
		}

		if ($this->path($name) !== null) {
			throw new InvalidContentType(sprintf('user/data/types already defines "%s".', $name));
		}

		if ($this->codeType($name) !== null) {
			throw new InvalidContentType(sprintf('"%s" is already defined in code.', $name));
		}

		$base = [
			...($kind === TypeKind::Collection ? [] : ['kind' => $kind->value]),
			...($folder === null || trim($folder, '/') === '' ? [] : ['folder' => trim($folder, '/')])
		];

		return $this->write($name, "{$this->directory()}/{$name}.json", $base, $changes, ['kind', 'folder']);
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

		$path = $this->path($name);
		$code = $this->codeType($name);

		if ($path === null && $code === null) {
			throw new InvalidContentType(sprintf('"%s" isn\'t defined in user/data/types, so it can\'t be changed here.', $name));
		}

		try {
			$data = $path === null ? [] : $this->data->loadFile($path);
		} catch (DataException $error) {
			throw new InvalidContentType(sprintf('%s Fix it by hand first.', $error->getMessage()), previous: $error);
		}

		if (LegacyTaxonomy::is($data)) {
			throw new InvalidContentType(sprintf('"%s" is still written as a taxonomy; migrate it first, on Site Health or with content:taxonomies --write.', $name));
		}

		return $this->write($name, $path ?? "{$this->directory()}/{$name}.json", $data, $changes, code: $code);
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

		$path = $this->path($name) ?? throw new InvalidContentType(sprintf('Nothing in user/data/types changes "%s".', $name));

		return $this->locked(function () use ($path): ContentTypes {
			$before = (string) @file_get_contents($path);

			if (! @unlink($path)) {
				throw new InvalidContentType(sprintf('Unable to delete %s.', $this->paths->relative($path)));
			}

			return $this->checked($path, $before);
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

		$path = $this->path($name) ?? throw new InvalidContentType(sprintf('"%s" isn\'t defined in user/data/types, so it can\'t be deleted here.', $name));

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

		return $this->locked(function () use ($path): ContentTypes {
			$before = (string) @file_get_contents($path);

			if (! @unlink($path)) {
				throw new InvalidContentType(sprintf('Unable to delete %s.', $this->paths->relative($path)));
			}

			return $this->checked($path, $before);
		});
	}

	/**
	 * Applies changes to a type's data, checks the type, and writes the
	 * changed options.
	 *
	 * @param  array<array-key, mixed> $data    The file's data.
	 * @param  array<string, mixed>    $changes
	 * @param  list<string>            $also    More keys to write as they are (a new type's `kind` and `folder`).
	 * @param  ?ContentType             $code    The type the code defines, when the file changes it.
	 * @throws InvalidContentType
	 */
	private function write(string $name, string $path, array $data, array $changes, array $also = [], ?ContentType $code = null): ContentTypes
	{
		$merged  = $code === null ? $data : $code->overriddenBy($data, $this->fields)->toArray();
		$written = [];
		$paths   = [];

		if ($code !== null && array_key_exists('fields', $changes) && ! $this->fieldsEditable($code)) {
			throw new InvalidContentType('Its fields include field classes from code, so they\'re changed in code.');
		}

		foreach ($changes as $key => $value) {
			if ($key === 'folder') {
				throw new InvalidContentType('A type\'s folder doesn\'t change here, since entries are filed by it; its folder pattern does ("folders").');
			}

			if ($key === 'folders') {
				$key   = 'folder';
				$value = self::folderWith($name, $merged, $value);
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

		return $this->locked(function () use ($path, $sets, $code, $left): ContentTypes {
			$before = is_file($path) ? (string) @file_get_contents($path) : null;

			if ($code !== null && $left === []) {
				if ($before !== null && ! @unlink($path)) {
					throw new InvalidContentType(sprintf('Unable to delete %s.', $this->paths->relative($path)));
				}

				return $this->checked($path, $before);
			}

			try {
				$next = DataFileKeys::edit($path, $before ?? '', $sets, self::OPTIONS, ['fields' => 3]);
			} catch (InvalidData $error) {
				throw new InvalidContentType(str_replace('The file', 'The type\'s file', $error->getMessage()), previous: $error);
			}

			try {
				$this->filesystem->writeAtomic($path, $next);
			} catch (Throwable $error) {
				throw new InvalidContentType(sprintf('Unable to write %s.', $this->paths->relative($path)), previous: $error);
			}

			return $this->checked($path, $before);
		});
	}

	/**
	 * A type's options as the file writes them: no field classes, and no
	 * prefix the folder already gives.
	 *
	 * @return array<string, mixed>
	 */
	private static function canonical(ContentType $type): array
	{
		$canonical = self::withoutClasses($type->toArray());
		$urls      = $canonical['urls'] ?? null;

		if (is_array($urls) && ($urls['prefix'] ?? null) === self::folderPrefix($type->folder)) {
			unset($urls['prefix']);
			$canonical['urls'] = $urls === [] ? null : $urls;
		}

		return $canonical;
	}

	/**
	 * Returns the `folder` a type's data has with a new folder pattern
	 * (`{year}`, D-629), or `null` for none: the type's own folder stays.
	 * `null` back when that's the default folder with no pattern.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidContentType
	 */
	private static function folderWith(string $name, array $data, mixed $pattern): ?string
	{
		if ($pattern !== null && ! is_string($pattern)) {
			throw new InvalidContentType('"folders" must be a folder pattern, such as {year}, or null.');
		}

		$folder  = $data['folder'] ?? $data['path'] ?? "_{$name}";
		$root    = FolderPattern::split(trim(is_string($folder) ? $folder : '', '/'))[0];
		$pattern = trim($pattern ?? '', '/');

		if ($pattern === '') {
			return $root === "_{$name}" ? null : $root;
		}

		return "{$root}/{$pattern}";
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
			'folder'       => $type->folder,
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
	 * Loads every type again, putting the file back when they don't fit
	 * together.
	 *
	 * @throws InvalidContentType
	 */
	private function checked(string $path, ?string $before): ContentTypes
	{
		try {
			return ($this->loader)()->load();
		} catch (InvalidContentType $error) {
			if ($before === null) {
				@unlink($path);
			} else {
				$this->filesystem->writeAtomic($path, $before);
			}

			throw $error;
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
	 * The prefix a folder gives a type's URLs: the folder without the
	 * `_` that starts its names.
	 */
	public static function folderPrefix(string $folder): string
	{
		return implode('/', array_map(static fn (string $segment): string => ltrim($segment, '_'), explode('/', $folder)));
	}

	/**
	 * Runs a write under the types lock.
	 *
	 * @template T
	 * @param  Closure(): T $write
	 * @return T
	 * @throws InvalidContentType
	 */
	private function locked(Closure $write): mixed
	{
		if (! is_dir($this->paths->cache)) {
			@mkdir($this->paths->cache, 0775, true);
		}

		$lock = @fopen("{$this->paths->cache}/types.lock", 'c');

		if ($lock === false || ! flock($lock, LOCK_EX)) {
			throw new InvalidContentType('Unable to take the content types lock.');
		}

		try {
			return $write();
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	private function directory(): string
	{
		return "{$this->paths->data}/" . ContentTypeLoader::DATA_DIRECTORY;
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
