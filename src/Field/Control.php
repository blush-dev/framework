<?php

/**
 * Field control.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

/**
 * How a person enters one value (D-766): the input and its own options,
 * such as a placeholder. A control never knows where its value is
 * stored, and it never checks values; its field's type does (D-769).
 * Controls are the abstract base of the Type enum + Registry + Factory +
 * Registrar pattern (`ControlType`, `ControlRegistry`, `ControlFactory`,
 * `ControlRegistrar`). Only core registers them for 2.x.
 *
 * A control describes itself as data (`toArray()`, and the static
 * `label()`, `description()`, and `section()` for the picker), so the
 * admin draws it in Vue and a site's form can draw it in PHP.
 *
 * Each control stores one value type (`type()`), and may imply options
 * of that type (`implies()`, such as a link's `format`), so a definition
 * that names only a control still has a type, and every check stays on
 * the type. `fits()` says whether it can edit a field; the controls a
 * saved field may switch to are the offered ones that fit it (D-768).
 *
 * A control's constructor takes only its own options, each with a
 * default, so `new $class()` is the control with none set.
 *
 * @phpstan-consistent-constructor
 */
abstract class Control
{
	/**
	 * Returns the control's registry key, such as `textarea`.
	 */
	abstract public static function name(): string;

	/**
	 * Returns the control's name for people, such as "Radio Buttons".
	 */
	abstract public static function label(): string;

	/**
	 * Returns what the control is for, in a sentence.
	 */
	public static function description(): string
	{
		return '';
	}

	/**
	 * Returns the picker's section the control is offered in, or `null`
	 * for one the picker doesn't offer (a control other screens use).
	 */
	public static function section(): ?ControlSection
	{
		return null;
	}

	/**
	 * Returns the value type a field gets when a definition names only
	 * this control, or `null` for a control that edits several types and
	 * so needs one named.
	 */
	public static function type(): ?string
	{
		return null;
	}

	/**
	 * Returns the value types the control can edit, for catalogs and the
	 * editor schemas: its own type, or the ones a control without one
	 * edits. `fits()` has the last word on a field.
	 *
	 * @return list<string>
	 */
	public static function stores(): array
	{
		return static::type() === null ? [] : [static::type()];
	}

	/**
	 * Returns the options of its type the control implies, such as a
	 * link's `['format' => 'url']`.
	 *
	 * @return array<string, mixed>
	 */
	public static function implies(): array
	{
		return [];
	}

	/**
	 * Returns the shape of value the control stores, for telling which
	 * controls a saved field may switch to (D-768): its type and the
	 * options it implies, as `text` or `text:{"format":"url"}`. Controls
	 * of one shape store the same value.
	 */
	public static function shape(): string
	{
		$type = static::type() ?? '';

		return static::implies() === [] ? $type : $type . ':' . json_encode(static::implies(), JSON_THROW_ON_ERROR);
	}

	/**
	 * Returns whether the control can edit a field.
	 */
	abstract public function fits(Field $field): bool;

	/**
	 * Fills in a definition that names this control: its type when none
	 * is named, and the options it implies when the type is its own. Keys
	 * already written are kept.
	 *
	 * @param  array<array-key, mixed> $data
	 * @return array<array-key, mixed>
	 */
	public static function complete(array $data): array
	{
		$type = static::type();

		if ($type === null || (isset($data['type']) && $data['type'] !== $type)) {
			return $data;
		}

		return [...static::implies(), ...$data, 'type' => $type];
	}

	/**
	 * Builds the control from a field definition, reading its own options.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidSchema
	 */
	public static function fromArray(array $data): static
	{
		return new static();
	}

	/**
	 * Returns the control's own definition keys, set ones only.
	 *
	 * @return array<string, mixed>
	 */
	public function options(): array
	{
		return [];
	}

	/**
	 * Returns JSON Schemas for the control's own definition keys, by key,
	 * for the editor schemas (D-206).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function definitionSchema(): array
	{
		return [];
	}

	/**
	 * Returns the control as a definition writes it: its key and options.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return ['control' => static::name(), ...$this->options()];
	}
}
