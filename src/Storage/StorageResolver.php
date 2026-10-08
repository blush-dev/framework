<?php

/**
 * Storage resolver.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage;

use Blush\Container\Container;

/**
 * Builds the class that implements a storage contract for an area
 * (D-642): the driver `StorageConfig` names for the area gives the class,
 * and the container builds it. Each driver is built once.
 */
final class StorageResolver
{
	/**
	 * The drivers built so far, by name.
	 *
	 * @var array<string, Storage>
	 */
	private array $drivers = [];

	public function __construct(
		private readonly StorageConfig $config,
		private readonly StorageDriverFactory $factory,
		private readonly Container $container
	) {}

	/**
	 * Builds a contract's class for an area.
	 *
	 * @template T of object
	 * @param  class-string<T> $contract
	 * @return T
	 * @throws StorageException When the driver is unknown or doesn't cover the contract.
	 */
	public function resolve(StorageArea $area, string $contract): object
	{
		$name  = $this->config->driverFor($area);
		$class = $this->driver($name)->bindings()[$contract] ?? throw new StorageException(sprintf(
			'The "%s" storage driver, named for %s, has no %s.',
			$name,
			$area->value,
			$contract
		));

		if (! is_a($class, $contract, true)) {
			throw new StorageException(sprintf('The "%s" storage driver gives %s for %s, which doesn\'t implement it.', $name, $class, $contract));
		}

		return $this->container->make($class);
	}

	/**
	 * Returns a driver by name, built once.
	 *
	 * @throws StorageException
	 */
	private function driver(string $name): Storage
	{
		return $this->drivers[$name] ??= $this->factory->make($name);
	}
}
