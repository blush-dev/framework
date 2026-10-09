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
use Blush\Container\Attributes\Defer;
use Blush\Data\DataKeys;
use Blush\Field\FieldConfig;
use Blush\Field\FieldFactory;
use Blush\Field\FieldSet;
use Blush\Field\FieldSetLoader;
use Blush\Field\FieldTargets;
use Blush\Field\InvalidSchema;
use Blush\Storage\Record\KeyedTable;
use Blush\Storage\Record\RecordException;

/**
 * Writes the field sets the site defines in data (D-337), the
 * `field_sets` table (D-678; on files `user/data/fields/{name}.json`),
 * for the admin: creates one, changes it, and deletes one.
 *
 * Changes are given by key (`label`, `description`, `targets`, `slot`,
 * and `fields`); `null` removes one. They're applied to the file's own data
 * and the set is built from that (`FieldSet::fromArray()`), so it's
 * checked as the loader checks it; each changed key is then written as
 * the set itself writes it (`FieldSet::toArray()`: no label its name
 * gives, and no field classes). Everything else in the file is left as
 * the author wrote it. Sets are JSON files (D-490, D-631).
 *
 * Since a set's fields join the places it targets, each change is checked
 * against all of them before it's kept: in a transaction of the table's
 * store, the record is written, the types are loaded again
 * (`ContentTypeLoader`), every target's schema is built with the new
 * sets (`FieldTargets`), and when a field clashes, the transaction puts
 * the record back.
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
		private FieldConfig $config,
		private FieldSetLoader $loaded,
		private FieldFactory $fields,
		private FieldTargets $targets,
		#[Defer(ContentTypeLoader::class)] private Closure $loader
	) {}

	/**
	 * Returns where a data set is kept (`user/data/fields/seo.json`), or
	 * `null` when the table has none by its name.
	 *
	 * @throws InvalidContentType When the name isn't a set name.
	 */
	public function location(string $name): ?string
	{
		if (preg_match(FieldSet::NAME_PATTERN, $name) !== 1) {
			throw new InvalidContentType(sprintf('"%s" isn\'t a field set name: use lowercase letters, digits, "_", and "-".', $name));
		}

		try {
			return $this->table()->has($name) ? $this->table()->location($name) : null;
		} catch (RecordException $error) {
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

		if ($this->location($name) !== null) {
			throw new InvalidContentType(sprintf('user/data/fields already defines "%s".', $name));
		}

		return $this->write($name, [], $changes);
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

		if ($this->location($name) === null) {
			throw new InvalidContentType(sprintf('"%s" isn\'t defined in user/data/fields, so it can\'t be changed here.', $name));
		}

		try {
			$data = $this->table()->find($name) ?? [];
		} catch (RecordException $error) {
			throw new InvalidContentType(sprintf('%s Fix it by hand first.', $error->getMessage()), previous: $error);
		}

		return $this->write($name, $data, $changes);
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

		if ($this->location($name) === null) {
			throw new InvalidContentType(sprintf('"%s" isn\'t defined in user/data/fields, so it can\'t be deleted here.', $name));
		}

		return $this->checked(function () use ($name): void {
			$this->table()->delete($name);
		});
	}

	/**
	 * Applies changes to a set's data, checks the set, and writes the
	 * changed keys.
	 *
	 * @param  array<array-key, mixed> $data    The record's data.
	 * @param  array<string, mixed>    $changes
	 * @throws InvalidContentType
	 */
	private function write(string $name, array $data, array $changes): ContentTypes
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

		return $this->checked(function () use ($name, $sets): void {
			$table = $this->table();

			$table->save($name, DataKeys::apply($table->find($name) ?? [], $sets));
		});
	}

	/**
	 * Runs a write in a transaction, loads every type again, and checks
	 * the sets against every other place they attach to (media kinds,
	 * D-341), which puts the record back when a set doesn't fit.
	 *
	 * @param  Closure(): void $write
	 * @throws InvalidContentType
	 */
	private function checked(Closure $write): ContentTypes
	{
		try {
			return $this->table()->transaction(function () use ($write): ContentTypes {
				$write();

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
			});
		} catch (RecordException $error) {
			throw new InvalidContentType($error->getMessage(), previous: $error);
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
	 * The data sets' table.
	 */
	private function table(): KeyedTable
	{
		return $this->loaded->records();
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
