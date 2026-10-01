<?php

/**
 * Bool field.
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

/**
 * A yes-or-no value. Besides booleans, it accepts `0` and `1` and the
 * strings `true`, `false`, `yes`, `no`, `on`, and `off`.
 */
final class BoolField extends Field
{
	public function __construct(string $name = '')
	{
		$this->name = $name;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function type(): string
	{
		return 'bool';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function typeLabel(): string
	{
		return 'Yes or no';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function typeDescription(): string
	{
		return 'true or false.';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function controls(): array
	{
		return [Control::Checkbox];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function normalize(mixed $value, FieldContext $context): mixed
	{
		if (is_bool($value)) {
			return $value;
		}

		$bool = match (is_string($value) ? strtolower(trim($value)) : $value) {
			1, '1', 'true', 'yes', 'on'  => true,
			0, '0', 'false', 'no', 'off' => false,
			default                      => null
		};

		return $bool ?? throw $this->invalid(sprintf('must be true or false, not %s.', is_string($value) ? "\"{$value}\"" : self::describe($value)));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function definitionSchema(array $field): array
	{
		return ['default' => ['type' => 'boolean']];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function valueType(): array
	{
		return ['anyOf' => [['type' => 'boolean'], ['enum' => [0, 1, '0', '1', 'true', 'false', 'yes', 'no', 'on', 'off']]]];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data, FieldFactory $factory): static
	{
		$definition = self::definition($data);

		return self::withShared(new static($definition->string('name')), $definition);
	}
}
