<?php

/**
 * Relations.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

/**
 * Every relation of a site, checked to fit its content types (D-585):
 * each names types that exist, and no source type has two relations
 * with one name or two reading one front matter key. A relation from
 * every type (`from: []`) is on each of them.
 */
final readonly class Relations
{
	/**
	 * Relations by source type and name.
	 *
	 * @var array<string, array<string, Relation>>
	 */
	private array $byType;

	/**
	 * @param  list<Relation> $relations
	 * @param  list<string>   $types     The site's content type names.
	 * @throws InvalidRelation
	 */
	public function __construct(public array $relations, public array $types)
	{
		$byType = array_fill_keys($types, []);
		$keys   = [];

		foreach ($relations as $relation) {
			foreach ([...$relation->from, ...$relation->to, ...($relation->inverse === false ? [] : $relation->inverse->types)] as $type) {
				if (! in_array($type, $types, true)) {
					throw new InvalidRelation(sprintf('Relation "%s" names a "%s" content type, which doesn\'t exist.', $relation->name, $type));
				}
			}

			foreach ($relation->from === [] ? $types : $relation->from as $type) {
				if (isset($byType[$type][$relation->name])) {
					throw new InvalidRelation(sprintf('Content type "%s" has two relations named "%s".', $type, $relation->name));
				}

				foreach ($relation->kind->isStructural() ? [] : $relation->keys() as $key) {
					if (isset($keys[$type][$key])) {
						throw new InvalidRelation(sprintf('Relations "%s" and "%s" both read "%s" on content type "%s".', $keys[$type][$key], $relation->name, $key, $type));
					}

					$keys[$type][$key] = $relation->name;
				}

				$byType[$type][$relation->name] = $relation;
			}
		}

		$this->byType = $byType;
	}

	/**
	 * Returns a type's relations, keyed by name.
	 *
	 * @return array<string, Relation>
	 */
	public function for(string $type): array
	{
		return $this->byType[$type] ?? [];
	}

	/**
	 * Returns a type's relation by name, or `null`.
	 */
	public function find(string $type, string $name): ?Relation
	{
		return $this->byType[$type][$name] ?? null;
	}

	/**
	 * Returns a type's relation by name.
	 *
	 * @throws InvalidRelation When the type has no such relation.
	 */
	public function get(string $type, string $name): Relation
	{
		return $this->find($type, $name) ?? throw new InvalidRelation(sprintf('Content type "%s" has no "%s" relation.', $type, $name));
	}

	/**
	 * Returns a relation by its key on a source type (`movie.actors`), or
	 * `null`.
	 */
	public function byKey(string $key): ?Relation
	{
		[$type, $name] = explode('.', $key, 2) + ['', ''];

		return $this->find($type, $name);
	}

	/**
	 * Returns the relation on a type that's written under a front matter
	 * key, as its field or an alias, or `null`.
	 */
	public function reading(string $type, string $key): ?Relation
	{
		return array_find(
			$this->for($type),
			static fn (Relation $relation): bool => ! $relation->kind->isStructural() && in_array($key, $relation->keys(), true)
		);
	}

	/**
	 * Returns the relations that may point at a type, keyed by their key
	 * on each source type (`movie.actors`).
	 *
	 * @return array<string, Relation>
	 */
	public function to(string $type): array
	{
		$found = [];

		foreach ($this->byType as $source => $relations) {
			foreach ($relations as $relation) {
				if ($relation->isTo($type)) {
					$found[$relation->key($source)] = $relation;
				}
			}
		}

		return $found;
	}

	/**
	 * Returns the relations as definition arrays, for a compiled cache.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function toArray(): array
	{
		return array_map(static fn (Relation $relation): array => $relation->toArray(), $this->relations);
	}

	/**
	 * Builds the relations from `toArray()`'s arrays.
	 *
	 * @param  list<array<array-key, mixed>> $relations
	 * @param  list<string>                  $types
	 * @throws InvalidRelation
	 */
	public static function fromArray(array $relations, array $types): self
	{
		return new self(array_map(Relation::fromArray(...), $relations), $types);
	}
}
