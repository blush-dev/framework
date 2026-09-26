<?php

/**
 * Null cache store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Cache;

use Override;

/**
 * Stores nothing, so every read misses. This is what a namespace gets
 * when caching is off (in development, by default).
 */
final class NullStore extends Store
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function read(string $key): ?Item
	{
		return null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function write(string $key, Item $item): bool
	{
		return false;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function remove(string $key): bool
	{
		return true;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function flush(): bool
	{
		return true;
	}
}
