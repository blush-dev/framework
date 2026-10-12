<?php

/**
 * Field set loader.
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
 * field sets, from the `field_sets` table (D-678; on files
 * `user/data/fields/*.json`, a set named after its file) unless
 * `FieldConfig::$dataSets` is off. Sets are only the site's: neither
 * code nor `config/` makes them (D-791).
 */
final readonly class FieldSetLoader
{
	/**
	 * The table of the sets the site defines in data (D-678).
	 */
	public const string TABLE = 'field_sets';

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
	 * The data sets' table: keyed by `name`, a record's other fields the
	 * set's definition as written.
	 */
	public static function table(): Table
	{
		return new Table(self::TABLE, StorageArea::Data, key: 'name', fields: ['name']);
	}

	/**
	 * The data sets, by name.
	 */
	public function records(): KeyedTable
	{
		return new KeyedTable($this->stores, self::table(), $this->clock);
	}

	/**
	 * Loads the registered fields and the sets.
	 *
	 * @throws InvalidSchema When a field or a set can't be read or isn't valid.
	 */
	public function load(): FieldSets
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

		$sets = [];

		foreach ($this->dataSets() as $set) {
			$sets[$set->name] = $set;
		}

		return new FieldSets($sets, new RegisteredFields($fields));
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
	 * Returns the data-defined sets, when the config allows them.
	 *
	 * @return list<FieldSet>
	 * @throws InvalidSchema
	 */
	private function dataSets(): array
	{
		if (! $this->config->dataSets) {
			return [];
		}

		$records = $this->records();

		try {
			$definitions = $records->all();
		} catch (RecordException $e) {
			throw new InvalidSchema($e->getMessage(), previous: $e);
		}

		$sets = [];

		foreach ($definitions as $name => $definition) {
			try {
				$sets[] = FieldSet::fromArray(['name' => $name, ...$definition], $this->fields);
			} catch (InvalidSchema $e) {
				throw new InvalidSchema(sprintf('%s: %s', $records->location($name), $e->getMessage()), previous: $e);
			}
		}

		return $sets;
	}
}
