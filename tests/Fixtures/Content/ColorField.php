<?php

/**
 * Color field fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Content;

use Override;
use Blush\Content\Schema\Field;
use Blush\Content\Schema\FieldContext;
use Blush\Content\Schema\FieldFactory;

/**
 * A hex color, standing in for a field type an extension adds.
 */
final class ColorField extends Field
{
	public function __construct(string $name = '')
	{
		$this->name = $name;
	}

	#[Override]
	public function type(): string
	{
		return 'color';
	}

	#[Override]
	public function normalize(mixed $value, FieldContext $context): mixed
	{
		if (! is_string($value) || preg_match('/^#[0-9a-f]{6}$/i', $value) !== 1) {
			throw $this->invalid('must be a hex color.');
		}

		return strtolower($value);
	}

	#[Override]
	public static function fromArray(array $data, FieldFactory $factory): static
	{
		$definition = self::definition($data);

		return self::withShared(new static($definition->string('name')), $definition);
	}
}
