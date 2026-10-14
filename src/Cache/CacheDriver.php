<?php

/**
 * Cache drivers.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Cache;

use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * The built-in cache drivers, keyed by the name config uses (the "Type
 * enum" of the enum + registry pattern, D-019).
 */
enum CacheDriver: string
{
	case File    = 'file';
	case PhpFile = 'php';
	case Apcu    = 'apcu';
	case Array   = 'array';
	case Null    = 'null';

	/**
	 * Returns the driver's store class.
	 *
	 * @return class-string<Store>
	 */
	public function store(): string
	{
		return match ($this) {
			self::File    => FileStore::class,
			self::PhpFile => PhpFileStore::class,
			self::Apcu    => ApcuStore::class,
			self::Array   => ArrayStore::class,
			self::Null    => NullStore::class
		};
	}

	/**
	 * Returns the driver's name, for reading.
	 */
	public function label(Messages $messages): Text
	{
		return match ($this) {
			self::File    => $messages->t('cache.cache_driver.file', 'Files'),
			self::PhpFile => $messages->t('cache.cache_driver.php_file', 'PHP files'),
			self::Apcu    => $messages->t('cache.cache_driver.apcu', 'APCu'),
			self::Array   => $messages->t('cache.cache_driver.array', 'Memory, for one request'),
			self::Null    => $messages->t('cache.cache_driver.null', 'Off')
		};
	}
}
