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
use Blush\Content\ContentConfig;
use Blush\Core\Paths;
use Blush\Data\DataLoader;
use Blush\Data\InvalidData;
use Blush\Extension\DefinitionClash;

/**
 * Loads the site's relation definitions (D-593), each replacing one of
 * the same name before it: extensions (`RelationSource`), then
 * `user/data/relations/*.{json,yaml,yml}` (a relation named after its
 * file) unless `ContentConfig::$dataTypes` is off. When two extensions
 * define a relation by one name, the first is kept and the clash is
 * returned with the others (D-597), so the site keeps loading.
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
	 * Loads the relations, keyed by name, with where each came from, and
	 * any two extensions defined by one name.
	 *
	 * @return array{array<string, Relation>, array<string, RelationOrigin>, list<DefinitionClash>}
	 * @throws InvalidRelation When one can't be read or isn't valid.
	 */
	public function load(): array
	{
		$relations = [];
		$origins   = [];
		$sources   = [];
		$clashes   = [];

		foreach ($this->extensionRelations() as [$relation, $source]) {
			if (isset($sources[$relation->name])) {
				$clashes[] = new DefinitionClash('relation', $relation->name, $sources[$relation->name], $source);

				continue;
			}

			$relations[$relation->name] = $relation;
			$origins[$relation->name]   = RelationOrigin::Extension;
			$sources[$relation->name]   = $source;
		}

		foreach ($this->dataRelations() as $relation) {
			$relations[$relation->name] = $relation;
			$origins[$relation->name]   = RelationOrigin::Data;
		}

		return [$relations, $origins, $clashes];
	}

	/**
	 * Returns the folder data relations are kept in.
	 */
	public function directory(): string
	{
		return $this->paths->data . '/' . self::DATA_DIRECTORY;
	}

	/**
	 * Returns the relations from extension sources, each with its
	 * source's class.
	 *
	 * @return iterable<array{Relation, string}>
	 * @throws InvalidRelation
	 */
	private function extensionRelations(): iterable
	{
		foreach ($this->container->tagged(RelationSource::TAG) as $source) {
			if (! $source instanceof RelationSource) {
				throw new InvalidRelation(sprintf('Services tagged "%s" must implement %s; %s does not.', RelationSource::TAG, RelationSource::class, get_debug_type($source)));
			}

			foreach ($source->relations() as $relation) {
				yield [$relation, $source::class];
			}
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
