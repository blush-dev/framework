<?php

/**
 * Storage driver factory.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage;

use Throwable;
use Blush\Container\Container;

/**
 * Builds the storage for a registered driver, through the container.
 */
final readonly class StorageDriverFactory
{
	public function __construct(
		private StorageDriverRegistry $registry,
		private Container $container
	) {}

	/**
	 * Builds a storage.
	 *
	 * @throws StorageException When the driver is unknown or can't be built.
	 */
	public function make(string $driver): Storage
	{
		$class = $this->registry->get($driver);

		if ($class === null) {
			throw new StorageException(sprintf(
				'Unknown storage driver "%s"; registered drivers: %s.',
				$driver,
				implode(', ', array_keys($this->registry->all()))
			));
		}

		try {
			return $this->container->build($class);
		} catch (StorageException $e) {
			throw $e;
		} catch (Throwable $e) {
			throw new StorageException(sprintf('Unable to build the "%s" storage: %s', $driver, $e->getMessage()), 0, $e);
		}
	}
}
