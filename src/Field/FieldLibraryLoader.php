<?php

/**
 * Field library loader.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

use Blush\Container\Container;
use Psr\Clock\ClockInterface;
use Blush\Storage\Record\KeyedTable;
use Blush\Storage\Record\RecordException;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageArea;

/**
 * Loads the fields code registers (D-790): core's (`FieldSource::CORE_TAG`,
 * owned by `core`), then extensions' (`FieldSource::TAG`); and the site's
 * field library (D-858), from the `fields` table (D-859; on files
 * `user/data/fields/*.json`, a field named after its file) unless
 * `FieldConfig::$library` is off. The library is only the site's:
 * neither code nor `config/` adds to it (D-791).
 */
final readonly class FieldLibraryLoader
{
	/**
	 * The table of the site's library fields (D-859).
	 */
	public const string TABLE = 'fields';

	/**
	 * The folder under `user/data` that keeps the table on files.
	 */
	public const string DATA_DIRECTORY = 'fields';

	public function __construct(
		private FieldConfig $config,
		private RecordStores $stores,
		private ClockInterface $clock,
		private FieldFactory $fields,
		private Container $container
	) {}

	/**
	 * The library's table: keyed by `name`, a record's other fields the
	 * field's definition as written.
	 */
	public static function table(): Table
	{
		return new Table(self::TABLE, StorageArea::Data, key: 'name', fields: ['name']);
	}

	/**
	 * The library's records, by name.
	 */
	public function records(): KeyedTable
	{
		return new KeyedTable($this->stores, self::table(), $this->clock);
	}

	/**
	 * Loads the registered fields and the library.
	 *
	 * @throws InvalidSchema When a field can't be read or isn't valid.
	 */
	public function load(): FieldLibrary
	{
		$fields = [];

		foreach ($this->sources(FieldSource::CORE_TAG) as $field) {
			if (! $field->isCore()) {
				throw new InvalidSchema(sprintf('Core\'s field "%s" is registered by "%s", not "%s".', $field->field->name, $field->owner, RegisteredField::CORE));
			}

			$fields[] = $field;
		}

		foreach ($this->sources(FieldSource::TAG) as $field) {
			if ($field->isCore()) {
				throw new InvalidSchema(sprintf('Field "%s" is registered by an extension as "%s"; name the extension, such as "acme/seo".', $field->field->name, RegisteredField::CORE));
			}

			$fields[] = $field;
		}

		$library = [];

		foreach ($this->libraryFields() as $field) {
			$library[$field->name()] = $field;
		}

		return new FieldLibrary($library, new RegisteredFields($fields));
	}

	/**
	 * Returns the fields the sources with a tag register: core's or
	 * extensions'.
	 *
	 * @return iterable<RegisteredField>
	 * @throws InvalidSchema
	 */
	private function sources(string $tag): iterable
	{
		foreach ($this->container->tagged($tag) as $source) {
			if (! $source instanceof FieldSource) {
				throw new InvalidSchema(sprintf(
					'Services tagged "%s" must implement %s; %s does not.',
					$tag,
					FieldSource::class,
					get_debug_type($source)
				));
			}

			yield from $source->fields();
		}
	}

	/**
	 * Returns the library's fields, when the config allows them.
	 *
	 * @return list<LibraryField>
	 * @throws InvalidSchema
	 */
	private function libraryFields(): array
	{
		if (! $this->config->library) {
			return [];
		}

		$records = $this->records();

		try {
			$definitions = $records->all();
		} catch (RecordException $e) {
			throw new InvalidSchema($e->getMessage(), previous: $e);
		}

		$fields = [];

		foreach ($definitions as $name => $definition) {
			try {
				$fields[] = LibraryField::fromArray([...$definition, 'name' => $name], $this->fields);
			} catch (InvalidSchema $e) {
				throw new InvalidSchema(sprintf('%s: %s', $records->location($name), $e->getMessage()), previous: $e);
			}
		}

		return $fields;
	}
}
