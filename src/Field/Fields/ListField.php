<?php

/**
 * List field.
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
use Blush\Field\InvalidField;
use Blush\Field\InvalidSchema;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * A list of values of one field type:
 *
 *     new ListField('template', new TextField())->aliases('view')
 *
 * A single value counts as a list of one, as in 1.x (D-078), so
 * `class: wide` and `class: [wide]` mean the same thing. Empty items are
 * dropped.
 */
final class ListField extends Field
{
	/**
	 * @throws InvalidSchema When the limits are reversed or below zero.
	 */
	public function __construct(
		string $name = '',
		public readonly Field $item = new TextField(),
		public readonly ?int $min = null,
		public readonly ?int $max = null
	) {
		$this->name = $name;

		if (($min !== null && $min < 0) || ($max !== null && $max < 0) || ($min !== null && $max !== null && $min > $max)) {
			throw new InvalidSchema(sprintf('Field "%s" must have limits of zero or more, the fewest no more than the most.', $name));
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function type(): string
	{
		return 'list';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function typeLabel(Messages $messages): Text
	{
		return $messages->t('field.list_field.type_label', 'List');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function typeDescription(Messages $messages): Text
	{
		return $messages->t('field.list_field.type_description', 'Several values.');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function normalize(mixed $value, FieldContext $context): mixed
	{
		if (! is_array($value)) {
			$value = [$value];
		} elseif (! array_is_list($value)) {
			throw $this->invalid($context->messages->t('field.list_field.map', 'must be a list, not a map.'));
		}

		$items = [];

		foreach ($value as $index => $item) {
			if ($item === null || $item === '') {
				continue;
			}

			try {
				$items[] = $this->item->normalize($item, $context);
			} catch (InvalidField $e) {
				throw $this->invalid($context->messages->t('field.list_field.item', 'item {number} {problem}', number: $index + 1, problem: $e->text, context: 'The problem is the rest of the sentence, such as "must be text, not a list."'));
			}
		}

		if ($this->min !== null && count($items) < $this->min) {
			throw $this->invalid($context->messages->t('field.list_field.too_few', 'must have at least {min, plural, one {# item} other {# items}}.', min: $this->min));
		}

		if ($this->max !== null && count($items) > $this->max) {
			throw $this->invalid($context->messages->t('field.list_field.too_many', 'must have at most {max, plural, one {# item} other {# items}}.', max: $this->max));
		}

		return $items;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function hydrate(mixed $value, FieldContext $context): mixed
	{
		return is_array($value)
			? array_map(fn (mixed $item): mixed => $this->item->hydrate($item, $context), $value)
			: $value;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function definitionSchema(array $field): array
	{
		return [
			'item'    => [...$field, 'description' => 'A field definition for each value; text by default.'],
			'min'     => ['type' => 'integer', 'minimum' => 0, 'description' => 'The fewest items a value may have.'],
			'max'     => ['type' => 'integer', 'minimum' => 0, 'description' => 'The most items a value may have.'],
			'default' => ['type' => 'array']
		];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function valueType(): array
	{
		$item = $this->item->valueSchema();

		// A single value counts as a list of one.
		return ['anyOf' => [['type' => 'array', 'items' => $item], $item]];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data, FieldFactory $factory): static
	{
		$definition = self::definition($data);
		$item       = $definition->map('item');

		return self::withShared(
			new static(
				$definition->string('name'),
				$item === [] ? new TextField() : $factory->fromArray($item),
				self::count($definition->number('min'), 'min', $definition->string('name')),
				self::count($definition->number('max'), 'max', $definition->string('name'))
			),
			$definition
		);
	}

	/**
	 * @inheritDoc
	 *
	 * @throws InvalidSchema
	 */
	#[Override]
	protected function options(): array
	{
		return ['item' => $this->item->toArray(), 'min' => $this->min, 'max' => $this->max];
	}


	/**
	 * Returns a limit as a whole number.
	 *
	 * @throws InvalidSchema
	 */
	private static function count(int|float|null $value, string $key, string $name): ?int
	{
		return $value === null || is_int($value) ? $value : throw new InvalidSchema(sprintf('Field "%s" "%s" must be a whole number.', $name, $key));
	}
}
