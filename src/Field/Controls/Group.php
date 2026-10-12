<?php

/**
 * Group control.
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
use Blush\Field\Fields\ObjectField;

/**
 * A few fields kept together under one heading: an object field, whose
 * fields' values are kept together under its key (D-780).
 */
final class Group extends Control
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function name(): string
	{
		return 'group';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function label(): string
	{
		return 'Group';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function description(): string
	{
		return 'A few fields kept together, their values under its key.';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function section(): ControlSection
	{
		return ControlSection::Structure;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function type(): string
	{
		return 'object';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function fits(Field $field): bool
	{
		return $field instanceof ObjectField;
	}
}
