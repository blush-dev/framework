<?php

/**
 * Component slots.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View\Component;

/**
 * A component's named slots, as its template sees them: `$slots->footer`
 * is the rendered HTML, or `''` when the slot wasn't filled, and
 * `isset($slots->footer)` tells whether it was. The default slot is
 * `$slot`.
 */
final readonly class Slots
{
	/**
	 * @param array<string, string> $slots
	 */
	public function __construct(private array $slots = [])
	{}

	/**
	 * Returns a slot's HTML, or `''`.
	 */
	public function __get(string $name): string
	{
		return $this->slots[$name] ?? '';
	}

	/**
	 * Returns whether a slot has content.
	 */
	public function __isset(string $name): bool
	{
		return $this->has($name);
	}

	/**
	 * Returns whether a slot has content.
	 */
	public function has(string $name): bool
	{
		return ($this->slots[$name] ?? '') !== '';
	}

	/**
	 * Returns every slot.
	 *
	 * @return array<string, string>
	 */
	public function all(): array
	{
		return $this->slots;
	}
}
