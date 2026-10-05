<?php

/**
 * Data field set writer.
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
use Blush\Field\FieldConfig;
use Blush\Field\FieldFactory;
use Blush\Field\FieldSet;
use Blush\Field\FieldSetLoader;
use Blush\Field\FieldTargets;
use Blush\Field\InvalidSchema;
use Blush\Support\Filesystem;

/**
 * Writes the field sets the site defines in data, `user/data/fields/{name}`
 * (D-337), for the admin: creates one, changes it, and deletes one.
 *
 * Changes are given by key (`label`, `description`, `targets`, `slot`,
 * and `fields`); `null` removes one. They're applied to the file's own data
 * and the set is built from that (`FieldSet::fromArray()`), so it's
 * checked as the loader checks it; each changed key is then written as
 * the set itself writes it (`FieldSet::toArray()`: no label its name
 * gives, and no field classes). Everything else in the file is left as
 * the author wrote it, comments included. A new set is a JSON file (D-490).
 *
 * Since a set's fields join the places it targets, each change is checked
 * against all of them before it's kept: the file is written, the types
 * are loaded again (`ContentTypeLoader`), every target's schema is built
 * with the new sets (`FieldTargets`), and when a field clashes, the file
 * is put back. Writes take the content types' lock (`DataTypeWriter`'s),
 * so a set and a type can't be written at once.
 */
final readonly class DataFieldSetWriter
{
	/**
	 * The keys the admin changes.
	 *
	 * @var list<string>
	 */
	public const array KEYS = ['label', 'description', 'targets', 'slot', 'fields'];

	/**
	 * @param Closure(): ContentTypeLoader $loader
	 */
	public function __construct(
		private Paths $paths,
		private FieldConfig $config,
		private DataLoader $data,
		private FieldFactory $fields,
		private Filesystem $filesystem,
		private FieldTargets $targets,
		#[Defer(ContentTypeLoader::class)] private Closure $loader
	) {}

	/**
	 * Returns a data set's file, or `null` when it has none.
	 *
	 * @throws InvalidContentType When the name isn't a set name.
	 */
	public function path(string $name): ?string
	{
		if (preg_match(FieldSet::NAME_PATTERN, $name) !== 1) {
			throw new InvalidContentType(sprintf('"%s" isn\'t a field set name: use lowercase letters, digits, "_", and "-".', $name));
		}

		try {
			return $this->data->find($this->directory(), $name);
		} catch (DataException $error) {
			throw new InvalidContentType($error->getMessage(), previous: $error);
		}
	}

	/**
	 * Creates a set in a new JSON file, and returns every type with it.
	 *
	 * @param  array<string, mixed> $changes
	 * @throws InvalidContentType When it can't be created or doesn't fit.
	 */
	public function create(string $name, array $changes): ContentTypes
	{
		$this->assertEnabled();

		if ($this->path($name) !== null) {
			throw new InvalidContentType(sprintf('user/data/fields already defines "%s".', $name));
		}

		return $this->write($name, "{$this->directory()}/{$name}.json", [], $changes);
	}

	/**
	 * Changes a data set, and returns every type with the change.
	 *
	 * @param  array<string, mixed> $changes
	 * @throws InvalidContentType When it can't be changed or doesn't fit.
	 */
	public function update(string $name, array $changes): ContentTypes
	{
		$this->assertEnabled();

		$path = $this->path($name) ?? throw new InvalidContentType(sprintf('"%s" isn\'t defined in user/data/fields, so it can\'t be changed here.', $name));

		try {
			$data = $this->data->loadFile($path);
		} catch (DataException $error) {
			throw new InvalidContentType(sprintf('%s Fix it by hand first.', $error->getMessage()), previous: $error);
		}

		return $this->write($name, $path, $data, $changes);
	}

	/**
	 * Deletes a data set's file, and returns the types without it. The
	 * values entries have for its fields stay in their files.
	 *
	 * @throws InvalidContentType When it isn't a data set.
	 */
	public function delete(string $name): ContentTypes
	{
		$this->assertEnabled();

		$path = $this->path($name) ?? throw new InvalidContentType(sprintf('"%s" isn\'t defined in user/data/fields, so it can\'t be deleted here.', $name));

		return $this->locked(function () use ($path): ContentTypes {
			$before = (string) @file_get_contents($path);

			if (! @unlink($path)) {
				throw new InvalidContentType(sprintf('Unable to delete %s.', $this->paths->relative($path)));
			}

			return $this->checked($path, $before);
		});
	}

	/**
	 * Applies changes to a set's data, checks the set, and writes the
	 * changed keys.
	 *
	 * @param  array<array-key, mixed> $data    The file's data.
	 * @param  array<string, mixed>    $changes
	 * @throws InvalidContentType
	 */
	private function write(string $name, string $path, array $data, array $changes): ContentTypes
	{
		$merged = $data;

		foreach ($changes as $key => $value) {
			if (! in_array($key, self::KEYS, true)) {
				throw new InvalidContentType(sprintf('"%s" isn\'t something the admin changes in a field set.', $key));
			}

			if ($value === null || $value === '' || $value === []) {
				unset($merged[$key]);
			} else {
				$merged[$key] = $value;
			}
		}

		try {
			$set = FieldSet::fromArray(['name' => $name, ...$merged], $this->fields);
		} catch (InvalidSchema $error) {
			throw new InvalidContentType($error->getMessage(), previous: $error);
		}

		$canonical = $set->toArray();

		// The registry knows field classes by type, so files never name them.
		$canonical['fields'] = array_map(self::withoutClasses(...), $canonical['fields']);

		$sets = [];

		foreach (array_keys($changes) as $key) {
			$value      = $canonical[$key] ?? null;
			$sets[$key] = $value === [] ? null : $value;
		}

		return $this->locked(function () use ($path, $sets): ContentTypes {
			$before = is_file($path) ? (string) @file_get_contents($path) : null;

			try {
				$next = DataFileKeys::edit($path, $before ?? '', $sets, [], ['fields' => 3]);
			} catch (InvalidData $error) {
				throw new InvalidContentType(str_replace('The file', 'The field set\'s file', $error->getMessage()), previous: $error);
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
	 * Loads every type again and checks the sets against every other
	 * place they attach to (media kinds, D-341), putting the file back
	 * when a set doesn't fit.
	 *
	 * @throws InvalidContentType
	 */
	private function checked(string $path, ?string $before): ContentTypes
	{
		try {
			$types = ($this->loader)()->load();

			try {
				$problems = $this->targets->problems($types->sets);
			} catch (InvalidSchema $error) {
				throw new InvalidContentType($error->getMessage(), previous: $error);
			}

			$first = array_first(array_merge(...array_values($problems)));

			if ($first !== null) {
				throw new InvalidContentType($first);
			}

			return $types;
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
	 * A field definition without its class, and its items' and nested
	 * fields' classes.
	 *
	 * @param  array<array-key, mixed> $field
	 * @return array<array-key, mixed>
	 */
	private static function withoutClasses(array $field): array
	{
		unset($field['class']);

		if (is_array($field['item'] ?? null)) {
			$field['item'] = self::withoutClasses($field['item']);
		}

		if (is_array($field['fields'] ?? null)) {
			$field['fields'] = array_map(static fn (mixed $nested): mixed => is_array($nested) ? self::withoutClasses($nested) : $nested, $field['fields']);
		}

		return $field;
	}

	/**
	 * Runs a write under the content types' lock.
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
		return "{$this->paths->data}/" . FieldSetLoader::DATA_DIRECTORY;
	}

	/**
	 * @throws InvalidContentType
	 */
	private function assertEnabled(): void
	{
		if (! $this->config->dataSets) {
			throw new InvalidContentType('FieldConfig "dataSets" is off, so sets in user/data/fields aren\'t read.');
		}
	}
}
