<?php

/**
 * Select control.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field\Controls;

use Override;
use Blush\Field\Control;
use Blush\Field\ControlSection;
use Blush\Field\Field;
use Blush\Field\Fields\EnumField;

/**
 * One of a set, chosen from a menu: a choice field.
 */
final class Select extends Control
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function name(): string
	{
		return 'select';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function label(): string
	{
		return 'Select Dropdown';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function description(): string
	{
		return 'One of a set, chosen from a menu.';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function section(): ControlSection
	{
		return ControlSection::Choices;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function type(): string
	{
		return 'enum';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function fits(Field $field): bool
	{
		return $field instanceof EnumField;
	}
}
