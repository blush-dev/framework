<?php

/**
 * Field controls.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

use NoDiscard;
use Blush\Field\Fields\ListField;
use Blush\Field\Fields\ObjectField;

/**
 * The controls a form chooses for its fields (D-795), by each field's
 * path: its name (`oven`), a group's field after the group's
 * (`details.servings`), and a list's item after the list's and `*`
 * (`gallery.*`, `gallery.*.caption`). A field it chooses none for is
 * drawn with its default (`ControlDefaults`).
 */
final readonly class FieldControls
{
	/**
	 * What a list's item adds to its list's path.
	 */
	public const string ITEM = '*';

	/**
	 * @param array<string, Control> $controls By path.
	 */
	public function __construct(public array $controls = [])
	{}

	/**
	 * Returns the control chosen for a path, or `null`.
	 */
	public function at(string $path): ?Control
	{
		return $this->controls[$path] ?? null;
	}

	/**
	 * Returns a copy with a control chosen for a path.
	 */
	#[NoDiscard]
	public function with(string $path, Control $control): self
	{
		return new self([...$this->controls, $path => $control]);
	}

	/**
	 * Returns fields as a field set's file writes them (D-795): each
	 * field's definition, with the control chosen for it as an object of
	 * its `name` and options, a group's fields and a list's item too.
	 * `ControlFactory::split()` reads them back.
	 *
	 * @param  iterable<Field> $fields
	 * @return list<array<string, mixed>>
	 */
	public function definitions(iterable $fields, string $parent = ''): array
	{
		$list = [];

		foreach ($fields as $field) {
			$list[] = $this->definition($field, FieldControls::path($parent, $field->name));
		}

		return $list;
	}

	/**
	 * Returns one field's definition at a path, with its control.
	 *
	 * @return array<string, mixed>
	 */
	private function definition(Field $field, string $path): array
	{
		$definition = $field->toArray();

		if ($field instanceof ObjectField) {
			$definition['fields'] = $this->definitions($field->schema->fields, $path);
		}

		if ($field instanceof ListField) {
			$definition['item'] = $this->definition($field->item, FieldControls::path($path, self::ITEM));
		}

		$control = $this->at($path);

		return $control === null ? $definition : [...$definition, 'control' => ['name' => $control::name(), ...$control->options()]];
	}

	/**
	 * Returns the path of a field under another's (`''` for the top).
	 */
	public static function path(string $parent, string $name): string
	{
		return $parent === '' ? $name : "{$parent}.{$name}";
	}
}
