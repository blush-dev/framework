<?php

/**
 * Job status.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

/**
 * Where a job is: waiting its turn, being worked on, finished, or
 * stopped by a failure (D-621).
 */
enum JobStatus: string
{
	case Queued  = 'queued';
	case Running = 'running';
	case Done    = 'done';
	case Failed  = 'failed';

	/**
	 * Returns the status's label.
	 */
	public function label(): string
	{
		return match ($this) {
			self::Queued  => 'Queued',
			self::Running => 'Running',
			self::Done    => 'Done',
			self::Failed  => 'Failed'
		};
	}

	/**
	 * Whether the job has stopped, one way or the other.
	 */
	public function isFinished(): bool
	{
		return $this === self::Done || $this === self::Failed;
	}
}
