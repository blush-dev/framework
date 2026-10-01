<?php

/**
 * Content type feed.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use Blush\Field\Definition;
use Blush\Field\InvalidSchema;

/**
 * A content type's feed settings: the taxonomy whose terms become each
 * item's categories (every taxonomy but `author` when unset), and how the
 * feed lists entries (newest file first, `FeedConfig::$limit` of them,
 * when unset). `fromArray()` also reads 1.x's `taxonomy` and
 * `collection` (D-078).
 */
final readonly class TypeFeed
{
	public function __construct(
		public ?string $categories = null,
		public ?Listing $listing = null
	) {}

	/**
	 * Builds feed settings from a map.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidContentType
	 */
	public static function fromArray(array $data, string $label): self
	{
		$data    = [...$data, 'categories' => $data['categories'] ?? $data['taxonomy'] ?? null, 'listing' => $data['listing'] ?? $data['collection'] ?? null];
		$unknown = array_diff(array_map(strval(...), array_keys($data)), ['categories', 'listing', 'taxonomy', 'collection']);

		if ($unknown !== []) {
			throw new InvalidContentType(sprintf('%s has unknown options: %s.', $label, implode(', ', $unknown)));
		}

		try {
			$feed    = new Definition($data, $label);
			$listing = $data['listing'] === null ? null : Listing::fromArray($feed->map('listing'), "{$label} listing");

			return new self($feed->nullableString('categories'), $listing);
		} catch (InvalidSchema $e) {
			throw new InvalidContentType($e->getMessage(), previous: $e);
		}
	}

	/**
	 * @return array{categories?: string, listing?: array<string, mixed>}
	 */
	public function toArray(): array
	{
		return array_filter(['categories' => $this->categories, 'listing' => $this->listing?->toArray()], static fn (mixed $value): bool => $value !== null && $value !== []);
	}
}
