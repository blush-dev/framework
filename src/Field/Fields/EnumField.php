<?php

/**
 * Enum field.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field\Fields;

use Override;
use Blush\Field\Control;
use Blush\Field\Field;
use Blush\Field\FieldContext;
use Blush\Field\FieldFactory;
use Blush\Field\InvalidSchema;

/**
 * One value from a fixed set of options. Matching ignores case, and the
 * option is stored as declared.
 */
final class EnumField extends Field
{
	/**
	 * @param  list<string> $options
	 * @throws InvalidSchema When there are no options.
	 */
	public function __construct(string $name = '', public readonly array $options = [])
	{
		$this->name = $name;

		if ($options === []) {
			throw new InvalidSchema(sprintf('Field "%s" must list its options.', $name));
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function type(): string
	{
		return 'enum';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function typeLabel(): string
	{
		return 'Choice';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function typeDescription(): string
	{
		return 'One of a set of values.';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function controls(): array
	{
		return [Control::Select, Control::Radios];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function normalize(mixed $value, FieldContext $context): mixed
	{
		if (is_string($value) || is_int($value)) {
			$match = array_find($this->options, static fn (string $option): bool => strcasecmp($option, trim((string) $value)) === 0);

			if ($match !== null) {
				return $match;
			}
		}

		throw $this->invalid(sprintf(
			'must be one of %s, not %s.',
			implode(', ', $this->options),
			is_string($value) ? "\"{$value}\"" : self::describe($value)
		));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function definitionSchema(array $field): array
	{
		return [
			'options' => [
				'type'        => 'array',
				'description' => 'The values allowed.',
				'items'       => ['type' => 'string'],
				'minItems'    => 1
			],
			'default' => ['type' => 'string']
		];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function valueType(): array
	{
		return ['enum' => $this->options];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data, FieldFactory $factory): static
	{
		$definition = self::definition($data);

		return self::withShared(new static($definition->string('name'), $definition->strings('options')), $definition);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function options(): array
	{
		return ['options' => $this->options];
	}
}
