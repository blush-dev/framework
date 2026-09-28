<?php

/**
 * Menu link factory.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Menu\Link;

use Throwable;
use Blush\Container\Container;
use Blush\Menu\MenuException;

/**
 * Builds registered link kinds through the container, once each.
 */
final class MenuLinkFactory
{
	/**
	 * Kinds built so far, by key.
	 *
	 * @var array<string, MenuLink>
	 */
	private array $links = [];

	public function __construct(
		private readonly MenuLinkRegistry $registry,
		private readonly Container $container
	) {}

	/**
	 * Returns the registered keys: the item keys that name a link.
	 *
	 * @return list<string>
	 */
	public function keys(): array
	{
		return array_keys($this->registry->all());
	}

	/**
	 * Returns a link kind.
	 *
	 * @throws MenuException When it's unknown or can't be built.
	 */
	public function make(string $key): MenuLink
	{
		if (isset($this->links[$key])) {
			return $this->links[$key];
		}

		$class = $this->registry->get($key) ?? throw new MenuException(sprintf('Unknown menu link kind "%s".', $key));

		try {
			return $this->links[$key] = $this->container->make($class);
		} catch (Throwable $error) {
			throw new MenuException(sprintf('Unable to build the "%s" menu link kind: %s', $key, $error->getMessage()), 0, $error);
		}
	}
}
