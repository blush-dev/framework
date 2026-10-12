<?php

/**
 * Form field.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

use Blush\Field\Fields\EnumField;
use Blush\Field\Fields\ListField;
use Blush\Field\Fields\ObjectField;

/**
 * A field drawn with a control (D-795): what a form holds, and what the
 * admin's forms are sent. A group's fields and a list's item are form
 * fields of their own, each with the control chosen for its path.
 */
final readonly class FormField
{
	/**
	 * @param list<FormField> $fields A group's fields.
	 * @param ?FormField      $item   A list's item.
	 */
	public function __construct(
		public Field $field,
		public Control $control,
		public array $fields = [],
		public ?FormField $item = null
	) {}

	/**
	 * Draws a field with the control chosen for its path, else its
	 * default.
	 */
	public static function of(Field $field, FieldControls $controls, ControlDefaults $defaults, string $path = ''): self
	{
		$path    = $path === '' ? $field->name : $path;
		$control = $controls->at($path) ?? $defaults->for($field);

		return new self(
			$field,
			$control,
			$field instanceof ObjectField
				? array_values(array_map(static fn (Field $child): self => self::of($child, $controls, $defaults, FieldControls::path($path, $child->name)), $field->schema->fields))
				: [],
			$field instanceof ListField ? self::of($field->item, $controls, $defaults, FieldControls::path($path, FieldControls::ITEM)) : null
		);
	}

	/**
	 * Draws each of a schema's fields.
	 *
	 * @return list<self>
	 */
	public static function all(Schema $schema, FieldControls $controls, ControlDefaults $defaults): array
	{
		return array_values(array_map(static fn (Field $field): self => self::of($field, $controls, $defaults), $schema->fields));
	}

	/**
	 * Returns it as the admin's forms take it (D-229, D-337): the field's
	 * definition, without a `class`, with its control and the control's
	 * options; a choice's option labels (`choices`); a group's `fields`;
	 * and a list's `item`.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		$field = $this->field;

		return [
			...array_diff_key($field->toArray(), ['class' => true]),
			...$this->control->toArray(),
			...($field instanceof EnumField ? ['options' => $field->options, 'choices' => array_combine($field->options, array_map($field->label(...), $field->options))] : []),
			...($field instanceof ObjectField ? ['fields' => array_map(static fn (self $child): array => $child->toArray(), $this->fields)] : []),
			...($this->item === null ? [] : ['item' => $this->item->toArray()])
		];
	}
}
