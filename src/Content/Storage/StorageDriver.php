<?php

/**
 * Content storage drivers.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Storage;

use Blush\Storage\StorageConfig;

/**
 * The built-in content storage drivers, keyed by the name `StorageConfig`
 * uses (the "Type enum" of the enum + registry pattern, D-019).
 */
enum StorageDriver: string
{
	case Filesystem = StorageConfig::FILESYSTEM;

	/**
	 * Returns the driver's storage class.
	 *
	 * @return class-string<ContentStorage>
	 */
	public function storage(): string
	{
		return match ($this) {
			self::Filesystem => FilesystemStorage::class
		};
	}
}
