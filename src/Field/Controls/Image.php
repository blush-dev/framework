<?php

/**
 * Image control.
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
use Blush\Field\Fields\MediaField;
use Blush\Media\MediaKind;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * One picture from the library: a media field of images.
 */
final class Image extends Control
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function name(): string
	{
		return 'image';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function label(Messages $messages): Text
	{
		return $messages->t('field.image.label', 'Image');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function description(Messages $messages): Text
	{
		return $messages->t('field.image.description', 'One picture from the library.');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function section(): ControlSection
	{
		return ControlSection::Media;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function type(): string
	{
		return 'media';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function implies(): array
	{
		return ['kind' => MediaKind::Image->value];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function fits(Field $field): bool
	{
		return $field instanceof MediaField && $field->kind === MediaKind::Image;
	}
}
