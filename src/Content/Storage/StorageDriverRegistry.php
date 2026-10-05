<?php

/**
 * Content storage driver registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Storage;

use Blush\Support\Registry;

/**
 * Maps driver names to storage classes. An extension adds a driver in a
 * `resolving()` callback, or in a provider's `boot()`:
 *
 *     $this->container->make(StorageDriverRegistry::class)->register('sqlite', SqliteStorage::class);
 *
 * @extends Registry<ContentStorage>
 */
final class StorageDriverRegistry extends Registry
{
	/**
	 * @inheritDoc
	 */
	protected const string CONTRACT = ContentStorage::class;
}
