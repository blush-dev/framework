<?php

/**
 * Content storage driver registrar.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Storage;

use Blush\Support\RegistrationException;

/**
 * Seeds the registry with the built-in drivers, leaving any name an
 * extension registered first alone.
 */
final readonly class StorageDriverRegistrar
{
	public function __construct(private StorageDriverRegistry $registry)
	{}

	/**
	 * Registers each built-in driver whose name is still free.
	 *
	 * @throws RegistrationException
	 */
	public function register(): void
	{
		foreach (StorageDriver::cases() as $driver) {
			$this->registry->registerIf($driver->value, $driver->storage());
		}
	}
}
