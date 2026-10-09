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
 * Loads the field sets from every source (D-337), each replacing a set of
 * the same name before it: extensions (`FieldSetSource`), then
 * `config/fields.php`, then the `field_sets` table (D-678; on files
 * `user/data/fields/*.json`, a set named after its file) unless
 * `FieldConfig::$dataSets` is off. Two
 * extensions can't define the same set.
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
	 * Loads the sets.
	 *
	 * @throws InvalidSchema When a set can't be read or isn't valid.
	 */
	public function load(): FieldSets
	{
		$sets    = [];
		$origins = [];

		foreach ($this->extensionSets() as $set) {
			if (isset($origins[$set->name])) {
				throw new InvalidSchema(sprintf('Two extensions define the "%s" field set.', $set->name));
			}

			$sets[$set->name]    = $set;
			$origins[$set->name] = FieldSetOrigin::Extension;
		}

		foreach ($this->configSets() as $set) {
			$sets[$set->name]    = $set;
			$origins[$set->name] = FieldSetOrigin::Config;
		}

		foreach ($this->dataSets() as $set) {
			$sets[$set->name]    = $set;
			$origins[$set->name] = FieldSetOrigin::Data;
		}

		return new FieldSets($sets, $origins);
	}

	/**
	 * Returns the sets from extension sources.
	 *
	 * @return iterable<FieldSet>
	 * @throws InvalidSchema
	 */
	private function extensionSets(): iterable
	{
		foreach ($this->container->tagged(FieldSetSource::TAG) as $source) {
			if (! $source instanceof FieldSetSource) {
				throw new InvalidSchema(sprintf(
					'Services tagged "%s" must implement %s; %s does not.',
					FieldSetSource::TAG,
					FieldSetSource::class,
					get_debug_type($source)
				));
			}

			yield from $source->fieldSets();
		}
	}

	/**
	 * Returns the config's sets, building those in array form with every
	 * registered field type.
	 *
	 * @return list<FieldSet>
	 * @throws InvalidSchema
	 */
	private function configSets(): array
	{
		$sets = $this->config->sets;

		foreach ($this->config->definitions as $definition) {
			try {
				$sets[] = FieldSet::fromArray($definition, $this->fields);
			} catch (InvalidSchema $e) {
				throw new InvalidSchema(sprintf('config/fields.php: %s', $e->getMessage()), previous: $e);
			}
		}

		return $sets;
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
