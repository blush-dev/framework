<?php

/**
 * Data field writer.
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
use Blush\Field\FieldConfig;
use Blush\Field\FieldFactory;
use Blush\Field\FieldLayout;
use Blush\Field\FieldLibraryLoader;
use Blush\Field\FieldTargets;
use Blush\Field\InvalidSchema;
use Blush\Field\LibraryField;
use Blush\Storage\Record\KeyedTable;
use Blush\Storage\Record\RecordException;
use Blush\Translation\Messages;

/**
 * Writes the site's field library (D-858), the `fields` table (D-859;
 * on files `user/data/fields/{name}.json`), for the admin: makes a field,
 * changes one, and deletes one.
 *
 * A field is given as the admin's forms send it (a control by its key,
 * D-795), built as the loader builds it (`LibraryField::fromArray()`),
 * and written whole as it writes itself, without field classes. Its name
 * is its key on every type that carries it, so it's fixed once made.
 * Fields are JSON files (D-631).
 *
 * Since a field joins every type that places it, each change is checked
 * against them before it's kept: in a transaction of the table's store,
 * the record is written, the types are loaded again
 * (`ContentTypeLoader`), every place's schema is built with the library
 * (`FieldTargets`), and when the field is left off one, the transaction
 * puts the record back.
 */
final readonly class DataFieldWriter
{
	/**
	 * @param Closure(): ContentTypeLoader $loader
	 */
	public function __construct(
		private FieldConfig $config,
		private FieldLibraryLoader $loaded,
		private FieldFactory $fields,
		private FieldTargets $targets,
		private DefinitionTables $tables,
		#[Defer(ContentTypeLoader::class)] private Closure $loader,
		private Messages $messages
	) {}

	/**
	 * Returns where a library field is kept (`user/data/fields/seo.json`),
	 * or `null` when the library has none by its name.
	 *
	 * @throws InvalidContentType When the name isn't a library field's.
	 */
	public function location(string $name): ?string
	{
		if (preg_match(LibraryField::NAME_PATTERN, $name) !== 1) {
			throw new InvalidContentType((string) $this->messages->t('content.data_field_writer.bad_name', '"{name}" isn\'t a field\'s key: use lowercase letters, digits, and "_", starting with a letter or "_".', name: $name));
		}

		try {
			return $this->table()->has($name) ? $this->table()->location($name) : null;
		} catch (RecordException $error) {
			throw new InvalidContentType($error->getMessage(), previous: $error);
		}
	}

	/**
	 * Makes a field in a new JSON file, and returns every type with it.
	 *
	 * @param  array<array-key, mixed> $field As the admin's forms send it, with its `name`.
	 * @throws InvalidContentType When it can't be made.
	 */
	public function create(array $field): ContentTypes
	{
		$this->assertEnabled();

		$name = is_string($field['name'] ?? null) ? $field['name'] : '';

		if ($this->location($name) !== null) {
			throw new InvalidContentType((string) $this->messages->t('content.data_field_writer.exists', 'The field library already has a "{name}" field.', name: $name));
		}

		return $this->write($name, $field);
	}

	/**
	 * Changes a library field, and returns every type with the change. Its
	 * key stays: a `name` sent with it must be the same.
	 *
	 * @param  array<array-key, mixed> $field As the admin's forms send it.
	 * @throws InvalidContentType When it can't be changed or doesn't fit.
	 */
	public function update(string $name, array $field): ContentTypes
	{
		$this->assertEnabled();

		if ($this->location($name) === null) {
			throw new InvalidContentType((string) $this->messages->t('content.data_field_writer.not_found', 'The field library has no "{name}" field.', name: $name));
		}

		if (($field['name'] ?? $name) !== $name) {
			throw new InvalidContentType((string) $this->messages->t('content.data_field_writer.key_fixed', 'A field\'s key is fixed once it\'s made, since every type that carries it reads it by its key; add the old one as an older name of a new field instead.'));
		}

		return $this->write($name, $field);
	}

	/**
	 * Deletes a library field's file, taking it off every type's layout
	 * (D-859), and returns the types without it. Entries keep their
	 * values for it in their files.
	 *
	 * @throws InvalidContentType When the library has no such field.
	 */
	public function delete(string $name): ContentTypes
	{
		$this->assertEnabled();

		if ($this->location($name) === null) {
			throw new InvalidContentType((string) $this->messages->t('content.data_field_writer.not_found', 'The field library has no "{name}" field.', name: $name));
		}

		return $this->checked(function () use ($name): void {
			$this->unplace($name);
			$this->table()->delete($name);
		});
	}

	/**
	 * Builds a field from what the admin sends, and writes it whole.
	 *
	 * @param  array<array-key, mixed> $field
	 * @throws InvalidContentType
	 */
	private function write(string $name, array $field): ContentTypes
	{
		try {
			$nested  = $this->fields->nest([[...$field, 'name' => $name]])[0];
			$library = LibraryField::fromArray($nested, $this->fields);
		} catch (InvalidSchema $error) {
			throw new InvalidContentType($error->getMessage(), previous: $error);
		}

		// The registry knows field classes by type, so files never name
		// them; the record's key is the field's name.
		$data = self::withoutClasses($library->toArray());

		unset($data['name']);

		return $this->checked(function () use ($name, $data): void {
			$this->table()->save($name, $data);
		});
	}

	/**
	 * Takes a field off every data type's layout, and off the layouts
	 * files lay over code types, its section staying.
	 *
	 * @throws InvalidContentType
	 */
	private function unplace(string $name): void
	{
		$types = $this->tables->types();

		try {
			foreach ($types->all() as $type => $data) {
				if (! is_array($data['fields'] ?? null)) {
					continue;
				}

				$layout = FieldLayout::fromArray($data['fields'], $type);

				if (! $layout->has($name)) {
					continue;
				}

				$without = $layout->without($name)->toArray();

				if ($without === []) {
					unset($data['fields']);
				} else {
					$data['fields'] = $without;
				}

				$types->save($type, $data);
			}
		} catch (InvalidSchema | RecordException $error) {
			throw new InvalidContentType($error->getMessage(), previous: $error);
		}
	}

	/**
	 * Runs a write in a transaction, loads every type again, and checks
	 * the library against every place, which puts the record back when a
	 * library field is left off one.
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
					$problems = $this->targets->problems($types->library);
				} catch (InvalidSchema $error) {
					throw new InvalidContentType($error->getMessage(), previous: $error);
				}

				// The library's own problems: a field an extension registers
				// that's left off isn't the site's to fix by saving a field.
				$first = array_first(array_merge(...array_values(array_intersect_key($problems, $types->library->all()))));

				if ($first !== null) {
					throw new InvalidContentType((string) $first);
				}

				return $types;
			});
		} catch (RecordException $error) {
			throw new InvalidContentType($error->getMessage(), previous: $error);
		}
	}

	/**
	 * A field definition without its class, and its item's and nested
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
	 * The library's table.
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
		if (! $this->config->library) {
			throw new InvalidContentType((string) $this->messages->t('content.data_field_writer.library_off', 'FieldConfig "library" is off, so the field library in user/data/fields isn\'t read.'));
		}
	}
}
