<?php

/**
 * Condition group.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

/**
 * Conditions joined one way: all must hold, or any. Groups nest, so a
 * query's conditions are a tree.
 */
final readonly class ConditionGroup
{
	/**
	 * @param list<Condition|ConditionGroup> $conditions
	 */
	public function __construct(
		public Junction $junction = Junction::All,
		public array $conditions = []
	) {}

	/**
	 * Returns the group with another condition or group.
	 */
	#[\NoDiscard]
	public function with(Condition|ConditionGroup $condition): self
	{
		return new self($this->junction, [...$this->conditions, $condition]);
	}

	/**
	 * Returns whether the group has no conditions: one of all of them
	 * matches every record, and one of any matches none.
	 */
	public function isEmpty(): bool
	{
		return $this->conditions === [];
	}
}
