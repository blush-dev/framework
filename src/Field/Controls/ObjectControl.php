<?php

/**
 * Object control.
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
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * An Object (D-858): a field made of other fields, whose values are kept
 * together under its key (D-780). Named for its field type, since
 * `object` can't name a PHP class.
 */
final class ObjectControl extends Control
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function name(): string
	{
		return 'object';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function label(Messages $messages): Text
	{
		return $messages->t('field.object.label', 'Object');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function description(Messages $messages): Text
	{
		return $messages->t('field.object.description', 'A field made of other fields, saved together under its key.');
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
