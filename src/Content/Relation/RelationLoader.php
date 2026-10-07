<?php

/**
 * Relation loader.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

use Blush\Container\Container;
use Blush\Content\Type\ContentConfig;
use Blush\Core\Paths;
use Blush\Data\DataLoader;
use Blush\Data\InvalidData;

/**
 * Loads the site's relation definitions (D-593), each replacing one of
 * the same name before it: extensions (`RelationSource`), then
 * `config/content.php`'s `relations`, then
 * `user/data/relations/*.{json,yaml,yml}` (a relation named after its
 * file) unless `ContentConfig::$dataTypes` is off. Two extensions can't
 * define the same relation.
 */
final readonly class RelationLoader
{
	/**
	 * The folder under `user/data` that holds data relations.
	 */
	public const string DATA_DIRECTORY = 'relations';

	public function __construct(
		private ContentConfig $config,
		private Paths $paths,
		private DataLoader $data,
		private Container $container
	) {}

	/**
	 * Loads the relations, keyed by name, with where each came from.
	 *
	 * @return array{array<string, Relation>, array<string, RelationOrigin>}
	 * @throws InvalidRelation When one can't be read or isn't valid.
	 */
	public function load(): array
	{
		$relations = [];
		$origins   = [];

		foreach ($this->extensionRelations() as $relation) {
			if (isset($origins[$relation->name])) {
				throw new InvalidRelation(sprintf('Two extensions define the "%s" relation.', $relation->name));
			}

			$relations[$relation->name] = $relation;
			$origins[$relation->name]   = RelationOrigin::Extension;
		}

		foreach ($this->config->relations as $relation) {
			$relations[$relation->name] = $relation;
			$origins[$relation->name]   = RelationOrigin::Config;
		}

		foreach ($this->dataRelations() as $relation) {
			$relations[$relation->name] = $relation;
			$origins[$relation->name]   = RelationOrigin::Data;
		}

		return [$relations, $origins];
	}

	/**
	 * Returns the folder data relations are kept in.
	 */
	public function directory(): string
	{
		return $this->paths->data . '/' . self::DATA_DIRECTORY;
	}

	/**
	 * Returns the relations from extension sources.
	 *
	 * @return iterable<Relation>
	 * @throws InvalidRelation
	 */
	private function extensionRelations(): iterable
	{
		foreach ($this->container->tagged(RelationSource::TAG) as $source) {
			if (! $source instanceof RelationSource) {
				throw new InvalidRelation(sprintf('Services tagged "%s" must implement %s; %s does not.', RelationSource::TAG, RelationSource::class, get_debug_type($source)));
			}

			yield from $source->relations();
		}
	}

	/**
	 * Returns the data relations, when the config allows data types.
	 *
	 * @return list<Relation>
	 * @throws InvalidRelation
	 */
	private function dataRelations(): array
	{
		if (! $this->config->dataTypes) {
			return [];
		}

		try {
			$definitions = $this->data->loadAll($this->directory());
		} catch (InvalidData $e) {
			throw new InvalidRelation($e->getMessage(), previous: $e);
		}

		$relations = [];

		foreach ($definitions as $name => $definition) {
			try {
				$relations[] = Relation::fromArray([...$definition, 'name' => (string) $name]);
			} catch (InvalidRelation $e) {
				throw new InvalidRelation(sprintf('user/data/%s/%s: %s', self::DATA_DIRECTORY, $name, $e->getMessage()), previous: $e);
			}
		}

		return $relations;
	}
}
