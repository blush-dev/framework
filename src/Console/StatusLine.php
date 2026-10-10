<?php

/**
 * Status line.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

/**
 * One row of `Output::status()`: an outcome, what was checked, what was
 * found, and, when there's something to do about it, a hint.
 */
final readonly class StatusLine
{
	public function __construct(
		public Outcome $outcome,
		public string $label,
		public string $detail = '',
		public string $hint = ''
	) {}
}
