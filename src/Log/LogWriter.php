<?php

/**
 * Log writer interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Log;

/**
 * Writes formatted log lines somewhere: a file, a stream, or nowhere.
 */
interface LogWriter
{
	/**
	 * Writes one formatted line (without a trailing newline).
	 */
	public function write(Level $level, string $line): void;
}
