<?php

/**
 * Content type listing.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use Blush\Content\Query\InvalidQuery;
use Blush\Storage\Record\Order;
use Blush\Content\Query\Query;
use Blush\Field\Definition;
use Blush\Field\InvalidSchema;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * How a listing page lists entries: a type's collection, a taxonomy's term
 * pages, or a feed. Unset settings fall back to whatever lists them (a
 * type's collection lists the type itself, ten to a page, in file-name
 * order):
 *
 *     new Listing(order: Order::Desc, perPage: Listing::ALL)
 *
 * `query` takes any other query argument, as 1.x wrote them (D-078), such
 * as `['terms' => ['category' => 'news']]`. `fromArray()` reads both these
 * names and a 1.x `collection` array (`orderby`, `number`, and the rest).
 */
final readonly class Listing
{
	/**
	 * The `perPage` that lists every entry on one page.
	 */
	public const int ALL = 0;

	/**
	 * The typed settings' 1.x query argument names.
	 *
	 * @var array<string, string>
	 */
	private const array ARGUMENTS = [
		'type'    => 'type',
		'orderBy' => 'orderby',
		'order'   => 'order',
		'perPage' => 'number'
	];

	/**
	 * @param  ?string              $type    The type to list, when not the owner's.
	 * @param  ?string              $orderBy `published` (a collection's default, newest first), `updated`, `title`, `slug`, `author`, `position`, or a field; never a file name (D-516).
	 * @param  ?Order               $order   The sort direction.
	 * @param  ?int                 $perPage Entries per page; `Listing::ALL` for every entry.
	 * @param  array<string, mixed> $query   Other query arguments, with their 1.x names.
	 * @param  Messages             $messages Writes why it's refused; English without a translator, for a listing in code.
	 * @throws InvalidContentType When the arguments aren't a valid query.
	 */
	public function __construct(
		public ?string $type = null,
		public ?string $orderBy = null,
		public ?Order $order = null,
		public ?int $perPage = null,
		public array $query = [],
		Messages $messages = new Messages()
	) {
		$typed = array_intersect(array_keys($query), self::ARGUMENTS);

		if ($typed !== []) {
			throw new InvalidContentType((string) $messages->t('content.listing.typed_query', 'Listing "query" can\'t set {arguments}; use the Listing options instead.', arguments: implode(', ', $typed)));
		}

		try {
			Query::fromArray($this->arguments());
		} catch (InvalidQuery $e) {
			throw new InvalidContentType((string) $messages->t('content.listing.invalid', 'Listing is invalid: {reason}', reason: $e->getMessage()), previous: $e);
		}
	}

	/**
	 * Returns the listing as 1.x query arguments, for `Query::fromArray()`,
	 * leaving out unset settings so the caller's defaults stand.
	 *
	 * @return array<string, mixed>
	 */
	public function arguments(): array
	{
		return [
			...$this->query,
			...array_filter([
				'type'    => $this->type,
				'orderby' => $this->orderBy,
				'order'   => $this->order?->value,
				'number'  => $this->perPage
			], static fn (mixed $value): bool => $value !== null)
		];
	}

	/**
	 * Builds a listing from its option names or a 1.x `collection` array.
	 *
	 * @param  array<array-key, mixed> $data
	 * @param  string|Text             $label    What the listing is, to start the messages about it: "Content type "post" listing".
	 * @throws InvalidContentType
	 */
	public static function fromArray(array $data, string|Text $label, Messages $messages): self
	{
		foreach (self::ARGUMENTS as $name => $argument) {
			if ($name !== $argument && array_key_exists($argument, $data)) {
				$data[$name] ??= $data[$argument];
				unset($data[$argument]);
			}
		}

		$listing = new Definition($data, (string) $label);

		try {
			$type    = $listing->nullableString('type');
			$orderBy = $listing->nullableString('orderBy');
			$order   = $listing->nullableString('order');
			$perPage = $listing->number('perPage');
			$query   = [...$listing->map('query'), ...array_diff_key($data, [...self::ARGUMENTS, 'query' => true])];
		} catch (InvalidSchema $e) {
			throw new InvalidContentType($e->getMessage(), previous: $e);
		}

		if ($order !== null && Order::tryFrom(strtolower($order)) === null) {
			throw new InvalidContentType((string) $messages->t('content.listing.bad_order', '{subject} "order" must be "asc" or "desc".', subject: $label));
		}

		if ($perPage !== null && ! is_int($perPage)) {
			throw new InvalidContentType((string) $messages->t('content.listing.per_page_not_whole', '{subject} "perPage" must be a whole number.', subject: $label));
		}

		if (! array_all($query, static fn (mixed $value, mixed $key): bool => is_string($key))) {
			throw new InvalidContentType((string) $messages->t('content.listing.bad_query', '{subject} "query" must map query argument names to values.', subject: $label));
		}

		/** @var array<string, mixed> $query */
		return new self(
			type: $type,
			orderBy: $orderBy === null ? null : Query::orderKey($orderBy),
			order: $order === null ? null : Order::from(strtolower($order)),
			perPage: $perPage === null ? null : max(self::ALL, $perPage),
			query: $query,
			messages: $messages
		);
	}

	/**
	 * Returns the listing as an array that `fromArray()` accepts, leaving
	 * out unset settings.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return array_filter([
			'type'    => $this->type,
			'orderBy' => $this->orderBy,
			'order'   => $this->order?->value,
			'perPage' => $this->perPage,
			'query'   => $this->query
		], static fn (mixed $value): bool => $value !== null && $value !== []);
	}
}
