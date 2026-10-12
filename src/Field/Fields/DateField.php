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

namespace Blush\Field\Fields;

use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Blush\Field\Field;
use Blush\Field\FieldContext;
use Blush\Field\FieldFactory;

/**
 * A date and time, stored as a Unix timestamp and hydrated as a
 * `DateTimeImmutable` in the site timezone. Values are ISO 8601 strings
 * that start with a full date (`2026-05-01`, `2026-05-01 15:40:00 -5`,
 * `2026-05-01T15:40:00Z`), timestamps, or date objects. A value without an
 * offset is read in the site timezone (D-045). Relative strings such as
 * `tomorrow` are refused, so a date can't change on its own.
 *
 * A field without a time (`time: false`) keeps only the day: its values
 * are the start of that day in the site timezone.
 */
final class DateField extends Field
{
	public function __construct(string $name = '', public readonly bool $time = true)
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
	public static function typeLabel(): string
	{
		return 'Date and time';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function typeDescription(): string
	{
		return 'A date and time.';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function normalize(mixed $value, FieldContext $context): mixed
	{
		if (is_int($value)) {
			return $this->time ? $value : $this->day(DateTimeImmutable::createFromTimestamp($value), $context);
		}

		if ($value instanceof DateTimeInterface) {
			return $this->time ? $value->getTimestamp() : $this->day(DateTimeImmutable::createFromInterface($value), $context);
		}

		if (! is_string($value) || preg_match('/^\s*\d{4}-\d{2}-\d{2}/', $value) !== 1) {
			throw $this->invalid(sprintf('must be a date such as 2026-05-01 15:40:00, not %s.', is_string($value) ? "\"{$value}\"" : self::describe($value)));
		}

		try {
			$date = new DateTimeImmutable(trim($value), $context->timezone);
		} catch (DateMalformedStringException) {
			throw $this->invalid(sprintf('"%s" is not a valid date.', $value));
		}

		return $this->time ? $date->getTimestamp() : $this->day($date, $context);
	}

	/**
	 * Returns the start of a date's day in the site timezone.
	 */
	private function day(DateTimeImmutable $date, FieldContext $context): int
	{
		return $date->setTimezone($context->timezone)->setTime(0, 0)->getTimestamp();
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
	protected function valueType(): array
	{
		// YAML dates arrive as text in editors. The pattern sits with its
		// own type, not a type list, or PhpStorm skips it (D-212).
		return ['anyOf' => [['type' => 'string', 'pattern' => '^\\s*\\d{4}-\\d{2}-\\d{2}'], ['type' => 'integer']]];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data, FieldFactory $factory): static
	{
		$definition = self::definition($data);

		return self::withShared(new static($definition->string('name'), $definition->bool('time', true)), $definition);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function definitionSchema(array $field): array
	{
		return ['time' => ['type' => 'boolean', 'description' => 'Whether the value has a time; without one, only the day is kept.', 'default' => true]];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function options(): array
	{
		return ['time' => $this->time ? null : false];
	}
}
