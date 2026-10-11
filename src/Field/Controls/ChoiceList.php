<?php

/**
 * Choice list control base.
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
use Blush\Field\Fields\EnumField;
use Blush\Field\Fields\ListField;

/**
 * A control for any number of choices from a set: a list field whose
 * items are a choice. A definition that names only the control may write
 * the options on the field (`options`); they become its item's.
 */
abstract class ChoiceList extends Control
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function type(): string
	{
		return 'list';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function shape(): string
	{
		return 'list:enum';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function complete(array $data): array
	{
		$data = parent::complete($data);

		if (($data['type'] ?? null) === 'list' && ! isset($data['item']) && isset($data['options'])) {
			$data['item'] = ['type' => 'enum', 'options' => $data['options']];
			unset($data['options']);
		}

		return $data;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function fits(Field $field): bool
	{
		return $field instanceof ListField && $field->item instanceof EnumField;
	}
}
