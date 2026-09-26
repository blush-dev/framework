<?php

/**
 * Array cache store.
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
 * Keeps entries in memory for the rest of the process: for tests, and for
 * work repeated within one request or CLI run.
 */
final class ArrayStore extends Store
{
	/**
	 * @var array<string, Item>
	 */
	private array $items = [];

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function read(string $key): ?Item
	{
		return $this->items[$key] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function write(string $key, Item $item): bool
	{
		$this->items[$key] = $item;

		return true;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function remove(string $key): bool
	{
		unset($this->items[$key]);

		return true;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function flush(): bool
	{
		$this->items = [];

		return true;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function prune(): int
	{
		$now     = $this->now();
		$expired = array_filter($this->items, static fn (Item $item): bool => $item->isExpired($now));

		$this->items = array_diff_key($this->items, $expired);

		return count($expired);
	}
}
