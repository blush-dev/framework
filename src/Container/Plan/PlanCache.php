<?php

/**
 * Plan cache.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Container\Plan;

use Blush\Support\PhpArrayFile;

/**
 * Reads and writes compiled container plans as an opcache-friendly PHP file
 * (D-044). Only exportable plans are written; the rest are rebuilt from
 * reflection at runtime.
 */
final readonly class PlanCache
{
	public function __construct(private PhpArrayFile $file)
	{}

	/**
	 * Returns a planner backed by the compiled file, or a plain reflection
	 * planner when nothing has been compiled.
	 */
	public function planner(): Planner
	{
		$compiled = $this->file->read();

		if ($compiled === null) {
			return new ReflectionPlanner();
		}

		/** @var array<string, array<string, mixed>> $compiled */
		return new CompiledPlanner($compiled);
	}

	/**
	 * Compiles the given plans to the file, skipping any that can't be
	 * exported. Returns the number of plans written.
	 *
	 * @param iterable<ClassPlan> $plans
	 */
	public function write(iterable $plans): int
	{
		$compiled = [];

		foreach ($plans as $plan) {
			if ($plan->isExportable()) {
				$compiled[$plan->class] = $plan->toArray();
			}
		}

		ksort($compiled);

		$this->file->write($compiled);

		return count($compiled);
	}

	/**
	 * Deletes the compiled file.
	 */
	public function clear(): void
	{
		$this->file->delete();
	}
}
