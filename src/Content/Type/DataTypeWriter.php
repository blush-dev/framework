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
use Blush\Content\Writer\DataFileKeys;
use Blush\Core\Paths;
use Blush\Data\DataException;
use Blush\Data\DataLoader;
use Blush\Data\InvalidData;
use Blush\Field\FieldFactory;
use Blush\Support\Filesystem;

/**
 * Writes the types the site defines in data, `user/data/types/{name}`
 * (D-042, D-311), for the admin: creates one, changes the settings the
 * admin edits, and deletes one.
 *
 * Changes are given by canonical option name (`labels`, `description`,
 * `icon`, `prefix` for the URL prefix, `authorsWord` for the word author
 * archives sit under (`false` for none, D-329), `public`, `sitemap`,
 * `feed`, `authors`, `dateArchives`, `hierarchical`, `types`, and
 * `fields`); `null` removes one. They're applied to the file's own data and the type is built from
 * that (`ContentType::fromArray()`), so it's checked as the loader
 * checks it; each changed option is then written as the type itself
 * writes it (`ContentType::toArray()`), which leaves out defaults, under
 * the name the file already uses, replacing any 1.x name for it.
 * Everything else in the file is left as the author wrote it: a JSON
 * file keeps its other keys, and a YAML file its other lines, comments
 * included. A new type is a YAML file.
 *
 * Each change is checked against every other type before it's kept: the
 * file is written, all the types are loaded again (`ContentTypeLoader`),
 * and when they don't fit together (two types in one folder, a taxonomy
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
		'feed'         => [],
		'authors'      => [],
		'dateArchives' => ['date_archives', 'time_archives'],
		'hierarchical' => [],
		'types'        => ['term_collect'],
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
	 * Creates a type in a new YAML file, and returns every type with it.
	 *
	 * @param  array<string, mixed> $changes
	 * @throws InvalidContentType When it can't be created or doesn't fit.
	 */
	public function create(string $name, TypeKind $kind, ?string $folder, array $changes): ContentTypes
	{
		$this->assertEnabled();

		if ($kind === TypeKind::Pages || $kind === TypeKind::Authors) {
			throw new InvalidContentType(sprintf('The site has one %s type; create a collection or a taxonomy.', $kind === TypeKind::Pages ? 'page' : 'authors'));
		}

		if ($this->path($name) !== null) {
			throw new InvalidContentType(sprintf('user/data/types already defines "%s".', $name));
		}

		$base = [
			...($kind === TypeKind::Collection ? [] : ['kind' => $kind->value]),
			...($folder === null || trim($folder, '/') === '' ? [] : ['folder' => trim($folder, '/')])
		];

		return $this->write($name, "{$this->directory()}/{$name}.yaml", $base, $changes, ['kind', 'folder']);
	}

	/**
	 * Changes a data type's options, and returns every type with the
	 * change.
	 *
	 * @param  array<string, mixed> $changes
	 * @throws InvalidContentType When it can't be changed or doesn't fit.
	 */
	public function update(string $name, array $changes): ContentTypes
	{
		$this->assertEnabled();

		$path = $this->path($name) ?? throw new InvalidContentType(sprintf('"%s" isn\'t defined in user/data/types, so it can\'t be changed here.', $name));

		try {
			$data = $this->data->loadFile($path);
		} catch (DataException $error) {
			throw new InvalidContentType(sprintf('%s Fix it by hand first.', $error->getMessage()), previous: $error);
		}

		return $this->write($name, $path, $data, $changes);
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

		$path = $this->path($name) ?? throw new InvalidContentType(sprintf('"%s" isn\'t defined in user/data/types, so it can\'t be deleted here.', $name));

		// The taxonomies that group it say why it can't go yet.
		$grouping = array_filter(($this->loader)()->load()->taxonomies(), static fn (Taxonomy $taxonomy): bool => in_array($name, $taxonomy->types, true));

		if ($grouping !== []) {
			throw new InvalidContentType(sprintf(
				'%s %s it; take it out of their groups first.',
				implode(' and ', array_map(static fn (Taxonomy $taxonomy): string => $taxonomy->labels->plural, $grouping)),
				count($grouping) === 1 ? 'groups' : 'group'
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
	 * @throws InvalidContentType
	 */
	private function write(string $name, string $path, array $data, array $changes, array $also = []): ContentTypes
	{
		$merged  = $data;
		$written = [];

		foreach ($changes as $key => $value) {
			if ($key === 'prefix') {
				$key   = 'urls';
				$value = $this->urls($merged, $value);
			} elseif ($key === 'authorsWord') {
				$key   = 'urls';
				$value = $this->authorsWord($merged, $value);
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
		$canonical = self::withoutClasses($type->toArray());

		// A prefix the folder already gives is left out.
		$urls = $canonical['urls'] ?? null;

		if (is_array($urls) && ($urls['prefix'] ?? null) === self::folderPrefix($type->folder)) {
			unset($urls['prefix']);
			$canonical['urls'] = $urls === [] ? null : $urls;
		}

		$sets = [];

		foreach ([...$also, ...$written] as $key) {
			$sets[$key] = $canonical[$key] ?? null;
		}

		// A collection is the kind a type is without one.
		if (($sets['kind'] ?? null) === TypeKind::Collection->value) {
			$sets['kind'] = null;
		}

		return $this->locked(function () use ($path, $sets): ContentTypes {
			$before = is_file($path) ? (string) @file_get_contents($path) : null;

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
	 * The `urls` an author word change leaves: the file's own, with the
	 * word set, turned off (`false`), or back to the default (`null` or
	 * `''`).
	 *
	 * @param  array<array-key, mixed> $data
	 * @return array<array-key, mixed>|null
	 * @throws InvalidContentType When the type has no URLs, or the word isn't one.
	 */
	private function authorsWord(array $data, mixed $word): ?array
	{
		$urls = $data['urls'] ?? $data['routing'] ?? [];

		if ($urls === false) {
			throw new InvalidContentType('The type has no URLs of its own, so it has no author archives.');
		}

		if ($word !== null && $word !== false && ! is_string($word)) {
			throw new InvalidContentType('"authorsWord" must be a word, false, or null.');
		}

		$urls = is_array($urls) ? $urls : [];

		if ($word === null || (is_string($word) && trim($word, '/ ') === '') || (is_string($word) && trim($word, '/ ') === TypeUrls::AUTHORS)) {
			unset($urls['authors']);
		} else {
			$urls['authors'] = is_string($word) ? trim($word, '/ ') : false;
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
