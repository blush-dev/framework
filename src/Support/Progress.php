<?php

/**
 * Progress.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Support;

use Closure;

/**
 * Says how a longer task is going, for a service that takes a progress
 * callback (`?Closure(string $step, int $done, int $total): void`), such
 * as the console's `ProgressSteps`: the step it's on, named as what it's
 * doing ("Writing upload dates"), and how far along it is, a total of 0
 * when that isn't known yet. Without a callback, it says nothing.
 */
final readonly class Progress
{
	/**
	 * @param ?Closure(string, int, int): void $report
	 */
	public function __construct(private ?Closure $report = null)
	{}

	/**
	 * Says how far along a step is.
	 */
	public function step(string $step, int $done = 0, int $total = 0): void
	{
		if ($this->report !== null) {
			($this->report)($step, $done, $total);
		}
	}

	/**
	 * Starts a step and returns a callback for its counts, for a service
	 * that reports only those (`MediaIndexer::index()`), or `null` without
	 * a callback.
	 *
	 * @return ?Closure(int, int): void
	 */
	public function of(string $step): ?Closure
	{
		if ($this->report === null) {
			return null;
		}

		$this->step($step);

		return fn (int $done, int $total) => $this->step($step, $done, $total);
	}
}
