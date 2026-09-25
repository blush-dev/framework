<?php

/**
 * Verbosity.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

/**
 * How much a command writes. Each level shows its own messages and those of
 * every lower level; `Quiet` shows only what must always be shown.
 */
enum Verbosity: int
{
	case Quiet       = 0;
	case Normal      = 1;
	case Verbose     = 2;
	case VeryVerbose = 3;
	case Debug       = 4;

	/**
	 * Whether a message at `$level` is shown at this verbosity.
	 */
	public function shows(self $level): bool
	{
		return $level->value <= $this->value;
	}
}
