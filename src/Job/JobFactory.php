<?php

/**
 * Job factory.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

use Throwable;
use Blush\Container\Container;

/**
 * Builds registered jobs through the container, so a job's constructor
 * can ask for services.
 */
final readonly class JobFactory
{
	public function __construct(
		private JobRegistry $registry,
		private Container $container
	) {}

	/**
	 * Builds a job.
	 *
	 * @throws UnknownJob When nothing is registered under the key.
	 * @throws JobException When it can't be built.
	 */
	public function make(string $key): Job
	{
		$class = $this->registry->get($key) ?? throw UnknownJob::named($key);

		try {
			return $this->container->make($class);
		} catch (Throwable $error) {
			throw new JobException(sprintf('Unable to build the "%s" job: %s', $key, $error->getMessage()), 0, $error);
		}
	}

	/**
	 * Returns a job's label, or its key when it can't be built.
	 */
	public function label(string $key): string
	{
		try {
			return $this->make($key)->label();
		} catch (JobException) {
			return $key;
		}
	}

	/**
	 * Returns the class a job is built from, or `null` when nothing is
	 * registered under the key.
	 *
	 * @return ?class-string<Job>
	 */
	public function classOf(string $key): ?string
	{
		return $this->registry->get($key);
	}
}
