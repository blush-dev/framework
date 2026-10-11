<?php

/**
 * Number control.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field\Controls;

use Override;
use Blush\Field\ControlSection;
use Blush\Field\Field;
use Blush\Field\Fields\NumberField;

/**
 * A number typed into a box.
 */
final class Number extends TextBox
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function name(): string
	{
		return 'number';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function label(): string
	{
		return 'Number';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function description(): string
	{
		return 'A number typed into a box.';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function section(): ControlSection
	{
		return ControlSection::Numbers;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function type(): string
	{
		return 'number';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function fits(Field $field): bool
	{
		return $field instanceof NumberField;
	}
}
