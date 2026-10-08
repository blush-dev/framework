<?php

/**
 * Runner kind.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

/**
 * What ran the jobs (D-621), recorded with each run's time, so Site
 * Health and Tools can say whether cron is set up.
 */
enum RunnerKind: string
{
	case Cron   = 'cron';
	case Worker = 'worker';
	case Web    = 'web';
	case Admin  = 'admin';

	/**
	 * Returns the runner's label.
	 */
	public function label(): string
	{
		return match ($this) {
			self::Cron   => 'Cron (schedule:run)',
			self::Worker => 'Worker (jobs:work)',
			self::Web    => 'After page visits',
			self::Admin  => 'The admin'
		};
	}

	/**
	 * Whether the runner comes by on its own, without a visit or a person
	 * waiting: cron and a worker.
	 */
	public function isDependable(): bool
	{
		return $this === self::Cron || $this === self::Worker;
	}
}
