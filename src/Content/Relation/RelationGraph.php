<?php

/**
 * Relation graph.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

/**
 * Every link of a site, looked up from either end (D-585): one index for
 * every relation, keyed by the relation's key on the source's type
 * (`movie.actors`), forward (a source's targets, in order) and reverse
 * (the sources pointing at a target). It's the relations table a
 * database would have (D-486), kept as arrays like the content index.
 *
 * @phpstan-import-type LinkArray from Link
 * @phpstan-type GraphArray array{
 *     links: list<LinkArray>,
 *     forward: array<string, array<string, list<int>>>,
 *     reverse: array<string, array<string, list<int>>>
 * }
 */
final readonly class RelationGraph
{
	/**
	 * @param list<LinkArray>                         $links
	 * @param array<string, array<string, list<int>>> $forward Link offsets by source id and key, in position order.
	 * @param array<string, array<string, list<int>>> $reverse Link offsets by target id and key.
	 */
	private function __construct(
		private array $links,
		private array $forward,
		private array $reverse
	) {}

	/**
	 * Builds the graph from links.
	 *
	 * @param iterable<Link> $links
	 */
	public static function build(iterable $links): self
	{
		$arrays  = [];
		$forward = [];
		$reverse = [];

		foreach ($links as $link) {
			$arrays[] = $link->toArray();
		}

		usort($arrays, static fn (array $a, array $b): int => [$a['source'], $a['relation'], $a['position']] <=> [$b['source'], $b['relation'], $b['position']]);

		foreach ($arrays as $offset => $link) {
			$key = "{$link['type']}.{$link['relation']}";

			$forward[$link['source']][$key][] = $offset;
			$reverse[$link['target']][$key][] = $offset;
		}

		return new self($arrays, $forward, $reverse);
	}

	/**
	 * Returns an empty graph.
	 */
	public static function empty(): self
	{
		return new self([], [], []);
	}

	/**
	 * Returns a source's links, in order: in one relation by name, or in
	 * every one.
	 *
	 * @return list<Link>
	 */
	public function links(string $source, ?string $relation = null): array
	{
		$offsets = [];

		foreach ($this->forward[$source] ?? [] as $key => $found) {
			if ($relation === null || self::nameOf($key) === $relation) {
				$offsets = [...$offsets, ...$found];
			}
		}

		return $this->hydrate($offsets);
	}

	/**
	 * Returns the ids a source links to in a relation, in order.
	 *
	 * @return list<string>
	 */
	public function targets(string $source, string $relation): array
	{
		return array_map(static fn (Link $link): string => $link->target, $this->links($source, $relation));
	}

	/**
	 * Returns the links pointing at a target: through one relation's key
	 * (`movie.actors`), one relation by name on any type (`actors`), or
	 * every relation.
	 *
	 * @return list<Link>
	 */
	public function linksTo(string $target, ?string $relation = null): array
	{
		$offsets = [];

		foreach ($this->reverse[$target] ?? [] as $key => $found) {
			if ($relation === null || $key === $relation || self::nameOf($key) === $relation) {
				$offsets = [...$offsets, ...$found];
			}
		}

		return $this->hydrate($offsets);
	}

	/**
	 * Returns the ids of the sources pointing at a target, as `linksTo()`
	 * finds them, without repeats.
	 *
	 * @return list<string>
	 */
	public function sources(string $target, ?string $relation = null): array
	{
		return array_values(array_unique(array_map(static fn (Link $link): string => $link->source, $this->linksTo($target, $relation))));
	}

	/**
	 * Returns every link.
	 *
	 * @return list<Link>
	 */
	public function all(): array
	{
		return array_map(Link::fromArray(...), $this->links);
	}

	/**
	 * Returns the graph as an array for storage.
	 *
	 * @return GraphArray
	 */
	public function toArray(): array
	{
		return ['links' => $this->links, 'forward' => $this->forward, 'reverse' => $this->reverse];
	}

	/**
	 * Builds the graph from `toArray()`'s array.
	 *
	 * @param GraphArray $data
	 */
	public static function fromArray(array $data): self
	{
		return new self($data['links'], $data['forward'], $data['reverse']);
	}

	/**
	 * Returns links by offset.
	 *
	 * @param  list<int> $offsets
	 * @return list<Link>
	 */
	private function hydrate(array $offsets): array
	{
		return array_map(fn (int $offset): Link => Link::fromArray($this->links[$offset]), $offsets);
	}

	/**
	 * Returns the relation's name in a key (`actors` in `movie.actors`).
	 */
	private static function nameOf(string $key): string
	{
		return substr($key, (int) strpos($key, '.') + 1);
	}
}
