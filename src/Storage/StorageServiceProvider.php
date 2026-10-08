<?php

/**
 * Storage service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage;

use Override;
use Blush\Core\ServiceProvider;
use Blush\Storage\File\FileLayouts;
use Blush\Storage\File\FileTransactions;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\TableRegistry;

/**
 * Binds the storage drivers (D-486, D-642), and the record layer's
 * services (D-643): the stores by area, the table registry, and the
 * filesystem driver's layouts and transactions. Each subsystem binds its
 * own contracts through `ServiceProvider`'s `STORAGE`, which these
 * resolve.
 */
final class StorageServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		StorageDriverFactory::class,
		StorageResolver::class,
		RecordStores::class,
		TableRegistry::class,
		FileLayouts::class,
		FileTransactions::class
	];

	/**
	 * Binds the driver registry, seeded with the built-in drivers.
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singleton(
			StorageDriverRegistry::class,
			static function (): StorageDriverRegistry {
				$registry = new StorageDriverRegistry();
				new StorageDriverRegistrar($registry)->register();

				return $registry;
			}
		);
	}
}
