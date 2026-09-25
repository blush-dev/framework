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

/**
 * A content type's feed settings: an optional taxonomy whose terms get
 * feeds of their own, and query arguments for the feed's entries (1.x's
 * `feed` option, D-078).
 */
final readonly class TypeFeed
{
	/**
	 * @param array<string, mixed> $collection Query arguments for the feed.
	 */
	public function __construct(
		public ?string $taxonomy = null,
		public array $collection = []
	) {}

	/**
	 * @return array{taxonomy?: string, collection?: array<string, mixed>}
	 */
	public function toArray(): array
	{
		return array_filter(['taxonomy' => $this->taxonomy, 'collection' => $this->collection], static fn (mixed $value): bool => $value !== null && $value !== []);
	}
}
