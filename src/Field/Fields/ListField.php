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
use Blush\Field\Control;
use Blush\Field\Field;
use Blush\Field\FieldContext;
use Blush\Field\FieldFactory;
use Blush\Field\InvalidField;
use Blush\Field\InvalidSchema;

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
	public function __construct(string $name = '', public readonly Field $item = new TextField())
	{
		$this->name = $name;
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
	public static function typeLabel(): string
	{
		return 'List';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function typeDescription(): string
	{
		return 'Several values.';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function controls(): array
	{
		return [Control::Lines, Control::Checks, Control::Readonly];
	}

	/**
	 * @inheritDoc
	 *
	 * One per line takes items edited on one line (text, numbers, dates,
	 * slugs, choices); checkboxes take choices.
	 */
	#[Override]
	public function canUse(Control $control): bool
	{
		$oneLine = [Control::Text, Control::Mono, Control::Number, Control::Select, Control::Radios, Control::Date, Control::Reference, Control::Media];

		return match ($control) {
			Control::Lines    => ! $this->item instanceof self && in_array($this->item->editedWith(), $oneLine, true),
			Control::Checks   => $this->item instanceof EnumField,
			Control::Readonly => true,
			default           => false
		};
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
			throw $this->invalid('must be a list, not a map.');
		}

		$items = [];

		foreach ($value as $index => $item) {
			if ($item === null || $item === '') {
				continue;
			}

			try {
				$items[] = $this->item->normalize($item, $context);
			} catch (InvalidField $e) {
				throw $this->invalid(sprintf('item %d %s', $index + 1, $e->getMessage()));
			}
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
			new static($definition->string('name'), $item === [] ? new TextField() : $factory->fromArray($item)),
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
		return ['item' => $this->item->toArray()];
	}
}
