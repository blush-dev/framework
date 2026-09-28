<?php

/**
 * Region item factory.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Region\Item;

use Throwable;
use Blush\Container\Container;
use Blush\Region\RegionException;

/**
 * Builds registered region item kinds through the container, once each.
 */
final class RegionItemFactory
{
	/**
	 * Kinds built so far, by key.
	 *
	 * @var array<string, RegionItem>
	 */
	private array $items = [];

	public function __construct(
		private readonly RegionItemRegistry $registry,
		private readonly Container $container
	) {}

	/**
	 * Returns the registered keys: the item keys that name a kind.
	 *
	 * @return list<string>
	 */
	public function keys(): array
	{
		return array_keys($this->registry->all());
	}

	/**
	 * Returns an item kind.
	 *
	 * @throws RegionException When it's unknown or can't be built.
	 */
	public function make(string $key): RegionItem
	{
		if (isset($this->items[$key])) {
			return $this->items[$key];
		}

		$class = $this->registry->get($key) ?? throw new RegionException(sprintf('Unknown region item kind "%s".', $key));

		try {
			return $this->items[$key] = $this->container->make($class);
		} catch (Throwable $error) {
			throw new RegionException(sprintf('Unable to build the "%s" region item kind: %s', $key, $error->getMessage()), 0, $error);
		}
	}
}
