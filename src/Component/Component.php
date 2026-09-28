<?php

/**
 * Component base.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component;

use ReflectionObject;
use ReflectionProperty;

/**
 * A class-backed component (D-025): typed props through constructor
 * promotion, plus the same template a template-only component would use
 * (`components/{key}`). Use one when props need logic; otherwise a
 * template alone is enough.
 *
 * ```php
 * final class Card extends Component
 * {
 *     public function __construct(
 *         public string $title,
 *         public int $columns = 2
 *     ) {}
 * }
 * ```
 *
 * The component is built through the container, so its constructor can
 * also ask for services. Props given as strings (from Markdown directives)
 * are cast to a parameter's `int`, `float`, or `bool` type.
 */
abstract class Component
{
	/**
	 * What the component wraps, which decides how the admin's inserter
	 * writes it in Markdown (D-172).
	 */
	public const ComponentContent CONTENT = ComponentContent::None;

	/**
	 * Returns the view the component renders, or `null` for
	 * `components/{key}`.
	 */
	public function template(): ?string
	{
		return null;
	}

	/**
	 * Returns whether the component renders anything at all.
	 */
	public function shouldRender(): bool
	{
		return true;
	}

	/**
	 * Returns the variables its template gets: its public properties, by
	 * default. Override to add computed values.
	 *
	 * @return array<string, mixed>
	 */
	public function data(): array
	{
		$data = [];

		foreach (new ReflectionObject($this)->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
			if (! $property->isStatic() && $property->isInitialized($this)) {
				$data[$property->getName()] = $property->getValue($this);
			}
		}

		return $data;
	}
}
