<?php

/**
 * Refs.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

use NoDiscard;
use Blush\Support\Uuid;

/**
 * The id form of an entry's relations in a file (D-589): its `refs`
 * front matter, mapping each relation's written values to their targets'
 * ids. Blush writes it beside the written form whenever it writes a
 * file; a file written by hand may leave it out.
 *
 *     tags: [cooking, quick-meals]
 *     refs:
 *       tags:
 *         cooking: 0199a1b4-…
 *         quick-meals: 0199a1c8-…
 *
 * Relations are keyed by name, not by front matter key, so reading an
 * alias doesn't change where its ids are. Anything that isn't a map of
 * written values to ids is left out, never an error: the written form
 * still says what's linked.
 */
final readonly class Refs
{
	/**
	 * The front matter key.
	 */
	public const string FIELD = 'refs';

	/**
	 * @param array<string, array<string, string>> $map Ids by relation name and written value.
	 */
	public function __construct(public array $map = [])
	{}

	/**
	 * Builds refs from a file's `refs` front matter value, keeping only
	 * written values that map to valid ids (lowercased).
	 */
	public static function fromValue(mixed $value): self
	{
		$map = [];

		foreach (is_array($value) ? $value : [] as $relation => $ids) {
			if (! is_string($relation) || ! is_array($ids)) {
				continue;
			}

			foreach ($ids as $written => $id) {
				if (Uuid::isValid($id) && is_string($id) && (string) $written !== '') {
					$map[$relation][(string) $written] = strtolower($id);
				}
			}
		}

		return new self($map);
	}

	/**
	 * Returns the id a relation's written value maps to, or `null`.
	 */
	public function idFor(string $relation, string $written): ?string
	{
		return $this->map[$relation][$written] ?? null;
	}

	/**
	 * Returns a relation's map of written values to ids.
	 *
	 * @return array<string, string>
	 */
	public function for(string $relation): array
	{
		return $this->map[$relation] ?? [];
	}

	/**
	 * Returns a copy with a relation's map replaced, or removed when it's
	 * empty.
	 *
	 * @param array<string, string> $ids Ids by written value.
	 */
	#[NoDiscard]
	public function with(string $relation, array $ids): self
	{
		$map = $this->map;

		if ($ids === []) {
			unset($map[$relation]);
		} else {
			$map[$relation] = $ids;
		}

		return new self($map);
	}

	/**
	 * Returns whether no relation has ids.
	 */
	public function isEmpty(): bool
	{
		return $this->map === [];
	}

	/**
	 * Returns the front matter value Blush writes.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function toArray(): array
	{
		return $this->map;
	}
}
