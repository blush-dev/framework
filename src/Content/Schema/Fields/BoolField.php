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

namespace Blush\Content\Schema\Fields;

use Override;
use Blush\Content\Schema\Field;
use Blush\Content\Schema\FieldContext;
use Blush\Content\Schema\FieldFactory;

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
	public static function fromArray(array $data, FieldFactory $factory): static
	{
		$definition = self::definition($data);

		return self::withShared(new static($definition->string('name')), $definition);
	}
}
