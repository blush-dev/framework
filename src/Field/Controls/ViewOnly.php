<?php

/**
 * Read-only control.
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
use Blush\Field\Field;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * A value shown but not edited, for fields no control fits. Not offered in the picker.
 */
final class ViewOnly extends Control
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function name(): string
	{
		return 'readonly';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function label(Messages $messages): Text
	{
		return $messages->t('field.view_only.label', 'Read-only');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function description(Messages $messages): Text
	{
		return $messages->t('field.view_only.description', 'Shown, not edited.');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function fits(Field $field): bool
	{
		return true;
	}
}
