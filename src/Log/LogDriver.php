<?php

/**
 * Log driver.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Log;

/**
 * Where log lines go.
 */
enum LogDriver: string
{
	/** Append to a file under `storage/logs`. */
	case File = 'file';

	/** Write to `php://stderr`. */
	case Stderr = 'stderr';

	/** Discard everything. */
	case Null = 'null';
}
