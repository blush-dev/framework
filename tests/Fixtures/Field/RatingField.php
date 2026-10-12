<?php

/**
 * Fixture: a field type of a plugin's.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Field;

use Override;
use Blush\Field\Field;
use Blush\Field\FieldContext;
use Blush\Field\FieldFactory;

/**
 * A rating, as a plugin might add.
 */
final class RatingField extends Field
{
	public function __construct(string $name = '')
	{
		$this->name = $name;
	}

	#[Override]
	public function type(): string
	{
		return 'rating';
	}

	#[Override]
	public function normalize(mixed $value, FieldContext $context): mixed
	{
		return $value;
	}

	#[Override]
	public static function fromArray(array $data, FieldFactory $factory): static
	{
		return new self(is_string($data['name'] ?? null) ? $data['name'] : '');
	}
}
