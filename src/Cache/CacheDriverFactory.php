<?php

/**
 * Cache driver factory.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Cache;

use Throwable;
use Blush\Container\Container;

/**
 * Builds a store for a namespace with a registered driver, through the
 * container, so a driver's constructor can ask for services. Every
 * driver's constructor takes the `$namespace` it's built for.
 */
final readonly class CacheDriverFactory
{
	public function __construct(
		private CacheDriverRegistry $registry,
		private Container $container
	) {}

	/**
	 * Builds a store.
	 *
	 * @throws CacheException When the driver is unknown or can't be built.
	 */
	public function make(string $driver, string $namespace): Store
	{
		$class = $this->registry->get($driver);

		if ($class === null) {
			throw new CacheException(sprintf(
				'Unknown cache driver "%s"; registered drivers: %s.',
				$driver,
				implode(', ', array_keys($this->registry->all()))
			));
		}

		try {
			return $this->container->build($class, ['namespace' => $namespace]);
		} catch (CacheException $e) {
			throw $e;
		} catch (Throwable $e) {
			throw new CacheException(sprintf('Unable to build the "%s" cache store for "%s": %s', $driver, $namespace, $e->getMessage()), 0, $e);
		}
	}
}
