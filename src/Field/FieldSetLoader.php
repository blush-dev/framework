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
use Blush\Core\Paths;
use Blush\Data\DataLoader;
use Blush\Data\InvalidData;

/**
 * Loads the field sets from every source (D-337), each replacing a set of
 * the same name before it: extensions (`FieldSetSource`), then
 * `config/fields.php`, then `user/data/fields/*.json` (a set
 * named after its file) unless `FieldConfig::$dataSets` is off. Two
 * extensions can't define the same set.
 */
final readonly class FieldSetLoader
{
	/**
	 * The folder under `user/data` that holds data sets.
	 */
	public const string DATA_DIRECTORY = 'fields';

	public function __construct(
		private FieldConfig $config,
		private Paths $paths,
		private DataLoader $data,
		private FieldFactory $fields,
		private Container $container
	) {}

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

		try {
			$definitions = $this->data->loadAll($this->paths->data . '/' . self::DATA_DIRECTORY);
		} catch (InvalidData $e) {
			throw new InvalidSchema($e->getMessage(), previous: $e);
		}

		$sets = [];

		foreach ($definitions as $name => $definition) {
			$declared = $definition['name'] ?? $name;

			if ($declared !== $name) {
				throw new InvalidSchema(sprintf(
					'user/data/%s/%s names the field set "%s"; a data set is named after its file.',
					self::DATA_DIRECTORY,
					$name,
					is_scalar($declared) ? (string) $declared : get_debug_type($declared)
				));
			}

			try {
				$sets[] = FieldSet::fromArray(['name' => $name, ...$definition], $this->fields);
			} catch (InvalidSchema $e) {
				throw new InvalidSchema(sprintf('user/data/%s/%s: %s', self::DATA_DIRECTORY, $name, $e->getMessage()), previous: $e);
			}
		}

		return $sets;
	}
}
