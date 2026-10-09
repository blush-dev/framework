<?php

/**
 * Storage drivers.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage;

/**
 * The built-in storage drivers, keyed by the name `StorageConfig`
 * uses (the "Type enum" of the enum + registry pattern, D-019).
 */
enum StorageDriver: string
{
	case Filesystem = StorageConfig::FILESYSTEM;
	case Sqlite     = StorageConfig::SQLITE;

	/**
	 * Returns the driver's storage class.
	 *
	 * @return class-string<Storage>
	 */
	public function storage(): string
	{
		return match ($this) {
			self::Filesystem => FilesystemStorage::class,
			self::Sqlite     => SqliteStorage::class
		};
	}
}
