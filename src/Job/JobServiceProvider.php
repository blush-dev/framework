<?php

/**
 * Job service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

use Override;
use Blush\Core\ServiceProvider;

/**
 * Binds background jobs and the scheduler (D-621): the job registry and
 * schedule, seeded with the framework's jobs and tasks, the store (files,
 * unless a site or extension binds another), the queue (which a test
 * can swap for `Testing\RecordingQueue`), and the runners.
 * Extensions register jobs and schedule them in their own `boot()`.
 */
final class JobServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		JobFactory::class,
		JobRunner::class,
		Scheduler::class,
		WebRunner::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS_IF = [
		JobStore::class => FileJobStore::class,
		JobQueue::class => StoredJobQueue::class
	];

	/**
	 * Binds the registry and schedule, seeded.
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singleton(JobRegistry::class, static function (): JobRegistry {
			$registry = new JobRegistry();
			new JobRegistrar($registry)->register();

			return $registry;
		});

		$this->container->singleton(Schedule::class, static function (): Schedule {
			$schedule = new Schedule();
			JobRegistrar::schedule($schedule);

			return $schedule;
		});
	}
}
