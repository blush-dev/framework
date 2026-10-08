<?php

/**
 * Job registrar.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

use Blush\Support\RegistrationException;

/**
 * Seeds the registry with the framework's jobs, and a schedule with its
 * tasks, leaving any key an extension took first alone.
 */
final readonly class JobRegistrar
{
	public function __construct(private JobRegistry $registry)
	{}

	/**
	 * Registers each built-in job whose key is still free.
	 *
	 * @throws RegistrationException
	 */
	public function register(): void
	{
		foreach (JobType::cases() as $type) {
			$this->registry->registerIf($type->value, $type->className());
		}
	}

	/**
	 * Schedules the built-in jobs that run on their own, unless they're
	 * scheduled already.
	 */
	public static function schedule(Schedule $schedule): void
	{
		foreach (JobType::cases() as $type) {
			$frequency = $type->frequency();

			if ($frequency !== null) {
				$schedule->addIf($type->value, $frequency);
			}
		}
	}
}
