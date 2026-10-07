<?php

/**
 * Content config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;
use Blush\Content\Relation\InvalidRelation;
use Blush\Content\Relation\Relation;

/**
 * The site's content settings, from `config/content.php`:
 *
 *     return new ContentConfig(
 *         types: [
 *             new Collection('post', folder: '_posts', urls: new TypeUrls('archives')),
 *             new Collection('category', folder: 'topics', hierarchical: true, order: TypeOrder::Position)
 *         ],
 *         relations: [
 *             new Relation('category', RelationKind::Classify, from: ['post'], to: ['category'])
 *         ],
 *         home: 'post'
 *     );
 *
 * In array form (`fromArray()`, and the config cache), `types` are kept
 * as `definitions` and built when types are loaded, with every field type
 * extensions register; the config file runs before they do.
 *
 * `relations` are the site's relation definitions (D-593), locked in
 * the admin; more may be defined as data in `user/data/relations`
 * unless `dataTypes` is off.
 *
 * Types defined here are locked in the admin, and they replace built-in or
 * extension types of the same name. Types may also be defined as data in
 * `user/data/types` unless `dataTypes` is off; with `dataTypeUrls` off,
 * data types can't set their own URLs (D-042).
 *
 * With `autoIndex` on (the default), development requests refresh the
 * content index incrementally on first use; elsewhere, `content:index`
 * (or publishing) refreshes it.
 */
final readonly class ContentConfig implements Config
{
	/**
	 * @param  list<ContentType> $types           The site's types.
	 * @param  list<array<array-key, mixed>> $definitions The site's types in array form, each with a `name`.
	 * @param  ?string           $home            A type whose collection is the homepage.
	 * @param  bool              $dataTypes       Whether `user/data/types` is read.
	 * @param  bool              $dataTypeUrls    Whether data types may set `urls`.
	 * @param  list<string>      $disabled        Built-in types to leave out.
	 * @param  bool              $autoIndex       Whether development requests refresh the index.
	 * @param  list<Relation>    $relations       The site's relation definitions.
	 * @throws InvalidConfig
	 */
	public function __construct(
		public array $types = [],
		public ?string $home = null,
		public bool $dataTypes = true,
		public bool $dataTypeUrls = true,
		public array $disabled = [],
		public bool $autoIndex = true,
		public array $definitions = [],
		public array $relations = []
	) {
		$names = [];

		foreach ($definitions as $definition) {
			if (! is_string($definition['name'] ?? null) || $definition['name'] === '') {
				throw new InvalidConfig('ContentConfig type definitions must each have a "name".');
			}
		}

		$all = [...array_map(static fn (ContentType $type): string => $type->name, $types), ...array_column($definitions, 'name')];

		foreach ($all as $name) {
			if (isset($names[$name])) {
				throw new InvalidConfig(sprintf('ContentConfig defines the "%s" type more than once.', $name));
			}

			$names[$name] = true;
		}

		foreach ($disabled as $name) {
			$builtIn = BuiltInType::tryFrom($name);

			if ($builtIn === null || ! $builtIn->canDisable()) {
				throw new InvalidConfig(sprintf('ContentConfig can\'t disable "%s"; only these built-in types can be: %s.', $name, implode(', ', array_column(
					array_filter(BuiltInType::cases(), static fn (BuiltInType $type): bool => $type->canDisable()),
					'value'
				))));
			}
		}
	}

	/**
	 * @inheritDoc
	 *
	 * `types` may be a list of definitions with names or, as in 1.x, a map
	 * of names to definitions. `relations` is a list of relation
	 * definitions, or a map of names to them.
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['types', 'relations', 'home', 'dataTypes', 'dataTypeUrls', 'disabled', 'autoIndex']);

		$types = $data['types'] ?? [];

		if (! is_array($types)) {
			throw new InvalidConfig('ContentConfig "types" must be a list or map of type definitions.');
		}

		$definitions = [];

		foreach ($types as $key => $type) {
			if (! is_array($type)) {
				throw new InvalidConfig('ContentConfig "types" must hold type definitions.');
			}

			$definitions[] = is_string($key) ? ['name' => $key, ...$type] : $type;
		}

		$relations = $data['relations'] ?? [];

		if (! is_array($relations)) {
			throw new InvalidConfig('ContentConfig "relations" must be a list or map of relation definitions.');
		}

		$built = [];

		foreach ($relations as $key => $relation) {
			if (! is_array($relation)) {
				throw new InvalidConfig('ContentConfig "relations" must hold relation definitions.');
			}

			try {
				$built[] = Relation::fromArray(is_string($key) ? ['name' => $key, ...$relation] : $relation);
			} catch (InvalidRelation $e) {
				throw new InvalidConfig(sprintf('ContentConfig "relations": %s', $e->getMessage()), previous: $e);
			}
		}

		return new static(
			definitions: $definitions,
			relations: $built,
			home: $values->nullableString('home'),
			dataTypes: $values->bool('dataTypes', true),
			dataTypeUrls: $values->bool('dataTypeUrls', true),
			disabled: $values->stringList('disabled'),
			autoIndex: $values->bool('autoIndex', true)
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'types'           => [...array_map(static fn (ContentType $type): array => $type->toArray(), $this->types), ...$this->definitions],
			'relations'       => array_map(static fn (Relation $relation): array => $relation->toArray(), $this->relations),
			'home'            => $this->home,
			'dataTypes'       => $this->dataTypes,
			'dataTypeUrls'    => $this->dataTypeUrls,
			'disabled'        => $this->disabled,
			'autoIndex'       => $this->autoIndex
		];
	}
}
