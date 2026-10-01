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

/**
 * How a field is edited in the admin: a fixed vocabulary the admin draws
 * (D-337). Each field type lists the controls it can be edited with
 * (`Field::controls()`), and a definition may pick one with `control`.
 * There are no custom controls; an extension's field type picks from
 * these.
 */
enum Control: string
{
	case Text      = 'text';
	case Textarea  = 'textarea';
	case Mono      = 'mono';
	case Checkbox  = 'checkbox';
	case Number    = 'number';
	case Select    = 'select';
	case Radios    = 'radios';
	case Checks    = 'checks';
	case Date      = 'date';
	case Lines     = 'lines';
	case Reference = 'reference';
	case Media     = 'media';
	case Readonly  = 'readonly';

	/**
	 * A name for people, for the admin's field definition editor.
	 */
	public function label(): string
	{
		return match ($this) {
			self::Text      => 'One line',
			self::Textarea  => 'Several lines',
			self::Mono      => 'Code',
			self::Checkbox  => 'Checkbox',
			self::Number    => 'Number',
			self::Select    => 'Menu',
			self::Radios    => 'Radio buttons',
			self::Checks    => 'Checkboxes',
			self::Date      => 'Date picker',
			self::Lines     => 'One per line',
			self::Reference => 'Entry picker',
			self::Media     => 'Media picker',
			self::Readonly  => 'Read-only'
		};
	}
}
