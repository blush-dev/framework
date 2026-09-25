<?php

/**
 * Compiled planner.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Container\Plan;

use Override;
use ReflectionFunctionAbstract;

/**
 * Serves plans from a compiled array (loaded by `PlanCache`), hydrating each
 * one the first time it's asked for. Anything not compiled is delegated to
 * the fallback planner, so a stale or partial cache only costs reflection,
 * never correctness for new classes.
 */
final class CompiledPlanner implements Planner
{
	/**
	 * Plans hydrated so far, keyed by class name.
	 *
	 * @var array<string, ClassPlan>
	 */
	private array $hydrated = [];

	/**
	 * @param array<string, array<string, mixed>> $compiled Plan arrays keyed by class name.
	 */
	public function __construct(
		private readonly array $compiled,
		private readonly Planner $fallback = new ReflectionPlanner()
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function forClass(string $class): ClassPlan
	{
		if (isset($this->hydrated[$class])) {
			return $this->hydrated[$class];
		}

		return $this->hydrated[$class] = isset($this->compiled[$class])
			? ClassPlan::fromArray($this->compiled[$class])
			: $this->fallback->forClass($class);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function forFunction(ReflectionFunctionAbstract $function): array
	{
		return $this->fallback->forFunction($function);
	}
}
