<?php

/**
 * Null log writer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Log;

use Override;

/**
 * Discards every line.
 */
final class NullWriter implements LogWriter
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function write(Level $level, string $line): void
	{
	}
}
