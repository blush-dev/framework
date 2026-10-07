<?php

/**
 * Inverse side of a relation.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

use Blush\Content\Type\InvalidContentType;
use Blush\Content\Type\Listing;

/**
 * A relation seen from its targets (D-585): what a target calls the
 * entries that point at it, where they're listed, and how many may. It's
 * read-only; links are edited on the side that stores them (D-242).
 *
 * - `label` names the list ("Acted in"); `''` leaves it to the source
 *   type's plural label.
 * - `archive` is where the list is a page: `true` on the target's own
 *   page (a term's), a word for archives under each source type (a
 *   people field's, `/posts/authors/jane`), or `false` for none.
 * - `types` limits the source types a target's page lists, empty for
 *   every one (a taxonomy's `types`).
 * - `max` limits how many entries may point at one target, `null` for
 *   any number (an episode in one season, D-587).
 * - `listing` is how a target's page lists them (a term's page: order,
 *   number per page), as a type's listing page does (D-593).
 */
final readonly class Inverse
{
	/**
	 * @param  list<string> $types
	 * @throws InvalidRelation
	 */
	public function __construct(
		public string $label = '',
		public string|bool $archive = false,
		public array $types = [],
		public ?int $max = null,
		public Listing $listing = new Listing()
	) {
		if ($archive === '') {
			throw new InvalidRelation('A relation\'s archive word can\'t be empty; use false for no archive.');
		}

		if ($max !== null && $max < 1) {
			throw new InvalidRelation(sprintf('A relation\'s inverse max must be at least 1, not %d.', $max));
		}
	}

	/**
	 * Builds the inverse side from a definition array, as `toArray()`
	 * writes it.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidRelation
	 */
	public static function fromArray(array $data): self
	{
		$archive = $data['archive'] ?? false;
		$types   = $data['types'] ?? [];
		$max     = $data['max'] ?? null;
		$listing = $data['listing'] ?? [];

		try {
			$listing = Listing::fromArray(is_array($listing) ? $listing : [], 'A relation\'s inverse listing');
		} catch (InvalidContentType $e) {
			throw new InvalidRelation($e->getMessage(), previous: $e);
		}

		return new self(
			label: is_string($data['label'] ?? null) ? $data['label'] : '',
			archive: is_string($archive) || is_bool($archive) ? $archive : throw new InvalidRelation('A relation\'s inverse archive must be true, false, or a word.'),
			types: is_array($types) ? array_values(array_map(strval(...), array_filter($types, is_string(...)))) : [],
			max: is_int($max) || $max === null ? $max : throw new InvalidRelation('A relation\'s inverse max must be a number.'),
			listing: $listing
		);
	}

	/**
	 * Returns the inverse side as an array, leaving out defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return array_filter([
			'label'   => $this->label,
			'archive' => $this->archive,
			'types'   => $this->types,
			'max'     => $this->max,
			'listing' => $this->listing->toArray()
		], static fn (mixed $value): bool => $value !== '' && $value !== false && $value !== [] && $value !== null);
	}
}
