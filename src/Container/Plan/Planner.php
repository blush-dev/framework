<?php

/**
 * Planner interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Container\Plan;

use ReflectionException;
use ReflectionFunctionAbstract;

/**
 * Supplies the container with resolution plans. `ReflectionPlanner` builds them
 * from reflection; `CompiledPlanner` serves them from a compiled file and falls
 * back to reflection for anything it doesn't hold.
 */
interface Planner
{
	/**
	 * Returns the plan for a class.
	 *
	 * @param  class-string $class
	 * @throws ReflectionException When the class does not exist.
	 */
	public function forClass(string $class): ClassPlan;

	/**
	 * Returns the parameter plans for a function, method, or closure. Used
	 * by `Container::call()`; these plans are never compiled.
	 *
	 * @return list<ParameterPlan>
	 */
	public function forFunction(ReflectionFunctionAbstract $function): array;
}
