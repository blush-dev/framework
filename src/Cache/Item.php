<?php

/**
 * Cache item.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Cache;

/**
 * A stored value and when it expires (a Unix time, or `0` for never).
 * Drivers store items as `[expires, value]` arrays.
 */
final readonly class Item
{
	public function __construct(
		public mixed $value,
		public int $expires = 0
	) {}

	/**
	 * Rebuilds an item from `toArray()`'s output, or returns `null` for
	 * anything else (a damaged or foreign entry).
	 */
	public static function fromArray(mixed $data): ?self
	{
		return is_array($data) && array_is_list($data) && count($data) === 2 && is_int($data[0])
			? new self($data[1], $data[0])
			: null;
	}

	/**
	 * Returns the item as `[expires, value]`.
	 *
	 * @return array{int, mixed}
	 */
	public function toArray(): array
	{
		return [$this->expires, $this->value];
	}

	/**
	 * Returns whether the item has expired at a time.
	 */
	public function isExpired(int $now): bool
	{
		return $this->expires !== 0 && $this->expires <= $now;
	}
}
