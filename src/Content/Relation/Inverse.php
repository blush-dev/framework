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
use Blush\Translation\Messages;

/**
 * A relation seen from its targets (D-585): what a target calls the
 * entries that point at it, where they're listed, and how many may. It's
 * read-only; links are edited on the side that stores them (D-242).
 *
 * - `label` names the list ("Acted in"); `''` leaves it to the source
 *   type's plural label.
 * - `page` lists them on the target's own page (a term's, a profile's).
 * - `archive` is a word for archives under each source type
 *   (`/posts/authors/jane`, `/movies/directors/penny`), or `false` for
 *   none. Both may be on at once (D-602): a profile's page lists
 *   everything crediting them, and each type's archive what it does.
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
	 * @param  Messages     $messages Writes why it's refused; English without a translator, for one in code.
	 * @throws InvalidRelation
	 */
	public function __construct(
		public string $label = '',
		public bool $page = false,
		public string|false $archive = false,
		public array $types = [],
		public ?int $max = null,
		public Listing $listing = new Listing(),
		Messages $messages = new Messages()
	) {
		if ($archive !== false && preg_match('/^[a-z0-9][a-z0-9_-]*$/', $archive) !== 1) {
			throw new InvalidRelation((string) $messages->t('content.inverse.bad_archive', 'A relation\'s archive word must be a slug, such as "authors", or false for none; "{archive}" isn\'t.', archive: $archive));
		}

		if ($max !== null && $max < 1) {
			throw new InvalidRelation((string) $messages->t('content.inverse.max_below_one', 'A relation\'s inverse max must be at least 1, not {max, number, integer}.', max: $max));
		}
	}

	/**
	 * Builds the inverse side from a definition array, as `toArray()`
	 * writes it.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidRelation
	 */
	public static function fromArray(array $data, Messages $messages): self
	{
		$archive = $data['archive'] ?? false;
		$page    = $data['page'] ?? false;
		$types   = $data['types'] ?? [];
		$max     = $data['max'] ?? null;
		$listing = $data['listing'] ?? [];

		try {
			$listing = Listing::fromArray(is_array($listing) ? $listing : [], $messages->t('content.inverse.listing_subject', 'A relation\'s inverse listing'), $messages);
		} catch (InvalidContentType $e) {
			throw new InvalidRelation($e->getMessage(), previous: $e);
		}

		return new self(
			label: is_string($data['label'] ?? null) ? $data['label'] : '',
			page: is_bool($page) ? $page : throw new InvalidRelation((string) $messages->t('content.inverse.bad_page', 'A relation\'s inverse page must be true or false.')),
			archive: is_string($archive) || $archive === false ? $archive : throw new InvalidRelation((string) $messages->t('content.inverse.bad_archive_type', 'A relation\'s inverse archive must be a word or false; for the target\'s own page, use "page": true.')),
			types: is_array($types) ? array_values(array_map(strval(...), array_filter($types, is_string(...)))) : [],
			max: is_int($max) || $max === null ? $max : throw new InvalidRelation((string) $messages->t('content.inverse.max_not_number', 'A relation\'s inverse max must be a number.')),
			listing: $listing,
			messages: $messages
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
			'page'    => $this->page,
			'archive' => $this->archive,
			'types'   => $this->types,
			'max'     => $this->max,
			'listing' => $this->listing->toArray()
		], static fn (mixed $value): bool => $value !== '' && $value !== false && $value !== [] && $value !== null);
	}
}
