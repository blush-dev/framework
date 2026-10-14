<?php

/**
 * Entry picker control.
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
use Blush\Field\Fields\ReferenceField;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Entries of the type a reference holds, picked from a list: the field a relation adds to a schema, a collection's parent, or a translation's original (of the entry's own type, a reference to no type named). Not offered in the picker; entries are linked by relationships (D-767).
 */
final class Reference extends Control
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function name(): string
	{
		return 'reference';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function label(Messages $messages): Text
	{
		return $messages->t('field.reference.label', 'Entry Picker');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function description(Messages $messages): Text
	{
		return $messages->t('field.reference.description', 'Entries picked from a list.');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function fits(Field $field): bool
	{
		return $field instanceof ReferenceField;
	}
}
