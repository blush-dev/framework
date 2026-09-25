<?php

/**
 * Attribute specification.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Container\Plan;

use ReflectionAttribute;

/**
 * A declared attribute recorded as data (its class and constructor arguments)
 * rather than as a built instance, so it can be exported with the rest of a
 * resolution plan and instantiated only when the container needs it.
 *
 * @template T of object
 */
final readonly class AttributeSpec
{
	/**
	 * @param class-string<T>     $class
	 * @param array<array-key, mixed> $arguments
	 */
	public function __construct(
		public string $class,
		public array $arguments = []
	) {}

	/**
	 * Records a reflected attribute without instantiating it.
	 *
	 * @template A of object
	 * @param    ReflectionAttribute<A> $attribute
	 * @return   self<A>
	 */
	public static function fromReflection(ReflectionAttribute $attribute): self
	{
		return new self($attribute->getName(), $attribute->getArguments());
	}

	/**
	 * Builds the attribute instance.
	 *
	 * @return T
	 */
	public function newInstance(): object
	{
		return new ($this->class)(...$this->arguments);
	}

	/**
	 * Whether the arguments can be written to, and read back from, a
	 * compiled PHP file.
	 */
	public function isExportable(): bool
	{
		return Exportable::check($this->arguments);
	}

	/**
	 * @return array{class: class-string<T>, arguments: array<array-key, mixed>}
	 */
	public function toArray(): array
	{
		return ['class' => $this->class, 'arguments' => $this->arguments];
	}

	/**
	 * @param  array{class: class-string, arguments: array<array-key, mixed>} $data
	 * @return self<object>
	 */
	public static function fromArray(array $data): self
	{
		return new self($data['class'], $data['arguments']);
	}
}
