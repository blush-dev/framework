<?php

/**
 * Number field.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field\Fields;

use Override;
use Blush\Field\Field;
use Blush\Field\FieldContext;
use Blush\Field\FieldFactory;
use Blush\Field\InvalidSchema;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * A number, optionally a whole one and optionally within bounds. Numeric
 * strings are accepted.
 */
final class NumberField extends Field
{
	/**
	 * @throws InvalidSchema When the bounds are reversed.
	 */
	public function __construct(
		string $name = '',
		public readonly bool $integer = false,
		public readonly int|float|null $min = null,
		public readonly int|float|null $max = null,
		public readonly int|float|null $step = null
	) {
		$this->name = $name;

		if ($min !== null && $max !== null && $min > $max) {
			throw new InvalidSchema(sprintf('Field "%s" has a minimum above its maximum.', $name));
		}

		if ($step !== null && $step <= 0) {
			throw new InvalidSchema(sprintf('Field "%s" has a step that isn\'t above zero.', $name));
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function type(): string
	{
		return 'number';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function typeLabel(Messages $messages): Text
	{
		return $messages->t('field.number_field.type_label', 'Number');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function typeDescription(Messages $messages): Text
	{
		return $messages->t('field.number_field.type_description', 'A number.');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function normalize(mixed $value, FieldContext $context): mixed
	{
		if (is_string($value) && is_numeric(trim($value))) {
			$value = trim($value) + 0;
		}

		if (! is_int($value) && ! is_float($value)) {
			throw $this->invalid($context->messages->t('field.number_field.invalid', 'must be a number, not {value}.', value: self::describe($value, $context->messages)));
		}

		if ($this->integer) {
			if (is_float($value) && floor($value) !== $value) {
				throw $this->invalid($context->messages->t('field.number_field.not_whole', 'must be a whole number, not {value}.', value: $value));
			}

			$value = (int) $value;
		}

		if ($this->min !== null && $value < $this->min) {
			throw $this->invalid($context->messages->t('field.number_field.too_small', 'must be at least {min}.', min: $this->min));
		}

		if ($this->max !== null && $value > $this->max) {
			throw $this->invalid($context->messages->t('field.number_field.too_large', 'must be at most {max}.', max: $this->max));
		}

		if ($this->step !== null && ! $this->inStep($value)) {
			throw $this->invalid($context->messages->t('field.number_field.step', 'must go in steps of {step} from {from}.', step: $this->step, from: $this->min ?? 0));
		}

		return $value;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function definitionSchema(array $field): array
	{
		return [
			'integer' => ['type' => 'boolean', 'description' => 'Whether the value must be a whole number.'],
			'min'     => ['type' => 'number', 'description' => 'The smallest value allowed.'],
			'max'     => ['type' => 'number', 'description' => 'The largest value allowed.'],
			'step'    => ['type' => 'number', 'exclusiveMinimum' => 0, 'description' => 'How far apart values are, counted from the minimum (or zero).'],
			'default' => ['type' => 'number']
		];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function valueType(): array
	{
		return [
			'type' => $this->integer ? 'integer' : 'number',
			...($this->min === null ? [] : ['minimum' => $this->min]),
			...($this->max === null ? [] : ['maximum' => $this->max])
		];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data, FieldFactory $factory): static
	{
		$definition = self::definition($data);

		return self::withShared(
			new static(
				$definition->string('name'),
				integer: $definition->bool('integer'),
				min: $definition->number('min'),
				max: $definition->number('max'),
				step: $definition->number('step')
			),
			$definition
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function options(): array
	{
		return ['integer' => $this->integer ?: null, 'min' => $this->min, 'max' => $this->max, 'step' => $this->step];
	}

	/**
	 * Returns whether a value is a whole number of steps from the minimum,
	 * allowing for floating-point error.
	 */
	private function inStep(int|float $value): bool
	{
		$steps = ($value - ($this->min ?? 0)) / (float) $this->step;

		return abs($steps - round($steps)) < 1e-9;
	}
}
