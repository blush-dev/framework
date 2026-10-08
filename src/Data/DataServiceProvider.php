<?php

/**
 * Data service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Data;

use Blush\Core\ServiceProvider;
use Blush\Storage\StorageArea;

/**
 * Binds the data loader, which reads JSON files, and the data store, the
 * site's data area from the storage driver for data (D-642).
 */
final class DataServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		DataLoader::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array STORAGE = [
		DataStore::class => StorageArea::Data
	];
}
