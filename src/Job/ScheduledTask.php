<?php

/**
 * Scheduled task.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

/**
 * A job queued on a timetable (D-621): its key, how often, and the data
 * it's queued with.
 */
final readonly class ScheduledTask
{
	/**
	 * @param array<string, mixed> $data
	 */
	public function __construct(
		public string $job,
		public Frequency $frequency,
		public array $data = []
	) {}
}
