<?php

/**
 * Code control.
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
use Blush\Field\Fields\MediaField;
use Blush\Field\Fields\ReferenceField;
use Blush\Field\Fields\SlugField;
use Blush\Field\Fields\TextField;

/**
 * Text in a code font: any text, a slug, a media path, or slugs of entries. Not offered in the picker.
 */
final class Code extends Control
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function name(): string
	{
		return 'code';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function label(): string
	{
		return 'Code';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function description(): string
	{
		return 'Text in a code font.';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function stores(): array
	{
		return ['text', 'slug', 'media'];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function fits(Field $field): bool
	{
		return $field instanceof TextField || $field instanceof SlugField || $field instanceof MediaField || $field instanceof ReferenceField;
	}
}
