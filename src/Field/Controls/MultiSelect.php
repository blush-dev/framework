<?php

/**
 * Multi-select control.
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
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Any number from a set, chosen from a menu.
 */
final class MultiSelect extends ChoiceList
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function name(): string
	{
		return 'multiselect';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function label(Messages $messages): Text
	{
		return $messages->t('field.multi_select.label', 'Multi-Select');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function description(Messages $messages): Text
	{
		return $messages->t('field.multi_select.description', 'Any number from a set, chosen from a menu.');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function section(): ControlSection
	{
		return ControlSection::Choices;
	}
}
