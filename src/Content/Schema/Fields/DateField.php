<?php

/**
 * Date field.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Schema\Fields;

use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Blush\Content\Schema\Field;
use Blush\Content\Schema\FieldContext;
use Blush\Content\Schema\FieldFactory;

/**
 * A date and time, stored as a Unix timestamp and hydrated as a
 * `DateTimeImmutable` in the site timezone. Values are ISO 8601 strings
 * that start with a full date (`2026-05-01`, `2026-05-01 15:40:00 -5`,
 * `2026-05-01T15:40:00Z`), timestamps, or date objects. A value without an
 * offset is read in the site timezone (D-045). Relative strings such as
 * `tomorrow` are refused, so a date can't change on its own.
 */
final class DateField extends Field
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
		return 'date';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function normalize(mixed $value, FieldContext $context): mixed
	{
		if (is_int($value)) {
			return $value;
		}

		if ($value instanceof DateTimeInterface) {
			return $value->getTimestamp();
		}

		if (! is_string($value) || preg_match('/^\s*\d{4}-\d{2}-\d{2}/', $value) !== 1) {
			throw $this->invalid(sprintf('must be a date such as 2026-05-01 15:40:00, not %s.', is_string($value) ? "\"{$value}\"" : self::describe($value)));
		}

		try {
			return new DateTimeImmutable(trim($value), $context->timezone)->getTimestamp();
		} catch (DateMalformedStringException) {
			throw $this->invalid(sprintf('"%s" is not a valid date.', $value));
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function hydrate(mixed $value, FieldContext $context): mixed
	{
		return is_int($value)
			? DateTimeImmutable::createFromTimestamp($value)->setTimezone($context->timezone)
			: $value;
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
