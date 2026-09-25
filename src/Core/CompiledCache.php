<?php

/**
 * Compiled cache names.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Core;

/**
 * The compiled cache files `Bootstrap` writes to `storage/cache` (D-060).
 * Each case's value is its file name without the `.php` extension.
 */
enum CompiledCache: string
{
	case Config     = 'config';
	case Extensions = 'extensions';
	case Container  = 'container';
	case Routes     = 'routes';
}
