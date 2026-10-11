<?php

/**
 * Control factory.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

use NoDiscard;

/**
 * Builds the controls field definitions name (`control`), looking each
 * up by its key in the registry, and says which controls a field may
 * switch to (D-768). `FieldFactory` uses it for every field it builds:
 * `complete()` before (a definition naming only a control gets the
 * control's type, D-769), `apply()` after.
 *
 * Controls are value objects, so their classes' own `fromArray()` builds
 * them rather than the container.
 */
final readonly class ControlFactory
{
	public function __construct(private ControlRegistry $registry)
	{}

	/**
	 * Returns a factory of the core controls, for code that builds fields
	 * outside the container (tests, tools).
	 */
	public static function core(): self
	{
		$registry = new ControlRegistry();
		new ControlRegistrar($registry)->register();

		return new self($registry);
	}

	/**
	 * Fills in a definition from the control it names (its type, and the
	 * options the control implies), or returns it as it is.
	 *
	 * @param  array<array-key, mixed> $data
	 * @return array<array-key, mixed>
	 * @throws InvalidSchema
	 */
	public function complete(array $data): array
	{
		$class = $this->classOf($data);

		return $class === null ? $data : $class::complete($data);
	}

	/**
	 * Returns the field edited with the control its definition names, or
	 * the field as it is when it names none.
	 *
	 * @template T of Field
	 * @param    T                       $field
	 * @param    array<array-key, mixed> $data
	 * @return   T
	 * @throws   InvalidSchema
	 */
	#[NoDiscard]
	public function apply(Field $field, array $data): Field
	{
		$class = $this->classOf($data);

		return $class === null ? $field : $field->control($class::fromArray($data));
	}

	/**
	 * Returns every control class, by key.
	 *
	 * @return array<string, class-string<Control>>
	 */
	public function all(): array
	{
		return $this->registry->all();
	}

	/**
	 * Returns the controls the picker offers that can edit a field and
	 * store the same shape of value as its own, in the registry's order:
	 * the ones a saved field may switch to (D-768).
	 *
	 * @return list<class-string<Control>>
	 */
	public function switchesFor(Field $field): array
	{
		$shape = $field->editedWith()::shape();

		return array_values(array_filter(
			$this->registry->all(),
			static fn (string $class): bool => $class::section() !== null && $class::shape() === $shape && new $class()->fits($field)
		));
	}

	/**
	 * Returns the class of the control a definition names, or `null`.
	 *
	 * @param  array<array-key, mixed> $data
	 * @return class-string<Control>|null
	 * @throws InvalidSchema
	 */
	private function classOf(array $data): ?string
	{
		$name  = $data['control'] ?? null;
		$field = is_string($data['name'] ?? null) ? $data['name'] : '';

		if ($name === null) {
			return null;
		}

		if (! is_string($name)) {
			throw new InvalidSchema(sprintf('Field "%s" "control" must be a string.', $field));
		}

		return $this->registry->get($name) ?? throw new InvalidSchema(sprintf(
			'Field "%s" has an unknown control "%s"; controls are %s.',
			$field,
			$name,
			implode(', ', array_keys($this->registry->all()))
		));
	}
}
