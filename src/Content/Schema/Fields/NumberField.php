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

namespace Blush\Content\Schema\Fields;

use Override;
use Blush\Content\Schema\Field;
use Blush\Content\Schema\FieldContext;
use Blush\Content\Schema\FieldFactory;
use Blush\Content\Schema\InvalidSchema;

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
		public readonly int|float|null $max = null
	) {
		$this->name = $name;

		if ($min !== null && $max !== null && $min > $max) {
			throw new InvalidSchema(sprintf('Field "%s" has a minimum above its maximum.', $name));
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
	public function normalize(mixed $value, FieldContext $context): mixed
	{
		if (is_string($value) && is_numeric(trim($value))) {
			$value = trim($value) + 0;
		}

		if (! is_int($value) && ! is_float($value)) {
			throw $this->invalid(sprintf('must be a number, not %s.', self::describe($value)));
		}

		if ($this->integer) {
			if (is_float($value) && floor($value) !== $value) {
				throw $this->invalid(sprintf('must be a whole number, not %s.', $value));
			}

			$value = (int) $value;
		}

		if ($this->min !== null && $value < $this->min) {
			throw $this->invalid(sprintf('must be at least %s.', $this->min));
		}

		if ($this->max !== null && $value > $this->max) {
			throw $this->invalid(sprintf('must be at most %s.', $this->max));
		}

		return $value;
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
				max: $definition->number('max')
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
		return ['integer' => $this->integer ?: null, 'min' => $this->min, 'max' => $this->max];
	}
}
