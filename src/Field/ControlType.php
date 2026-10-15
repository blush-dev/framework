<?php

/**
 * Core control types.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

use Blush\Field\Controls\Audio;
use Blush\Field\Controls\Checkboxes;
use Blush\Field\Controls\Code;
use Blush\Field\Controls\Date;
use Blush\Field\Controls\Email;
use Blush\Field\Controls\File;
use Blush\Field\Controls\Icon;
use Blush\Field\Controls\Image;
use Blush\Field\Controls\Link;
use Blush\Field\Controls\ListControl;
use Blush\Field\Controls\Markdown;
use Blush\Field\Controls\MultiSelect;
use Blush\Field\Controls\Number;
use Blush\Field\Controls\ObjectControl;
use Blush\Field\Controls\Radios;
use Blush\Field\Controls\Reference;
use Blush\Field\Controls\Select;
use Blush\Field\Controls\Text;
use Blush\Field\Controls\Textarea;
use Blush\Field\Controls\Toggle;
use Blush\Field\Controls\Video;
use Blush\Field\Controls\ViewOnly;

/**
 * The core controls, keyed by the `control` used in definitions (the
 * "Type enum" of the pattern, D-019, D-766), in the picker's order, with
 * each type's default control before the others that store it. The last
 * three aren't offered in the picker: other screens use them (code, an
 * entry picker, and read-only).
 */
enum ControlType: string
{
	case Object      = 'object';
	case List        = 'list';
	case Text        = 'text';
	case Textarea    = 'textarea';
	case Markdown    = 'markdown';
	case Link        = 'link';
	case Email       = 'email';
	case Select      = 'select';
	case Radios      = 'radios';
	case Checkboxes  = 'checkboxes';
	case MultiSelect = 'multiselect';
	case Toggle      = 'toggle';
	case Number      = 'number';
	case Date        = 'date';
	case Image       = 'image';
	case Video       = 'video';
	case Audio       = 'audio';
	case File        = 'file';
	case Icon        = 'icon';
	case Code        = 'code';
	case Reference   = 'reference';
	case Readonly    = 'readonly';

	/**
	 * Returns the control's class.
	 *
	 * @return class-string<Control>
	 */
	public function className(): string
	{
		return match ($this) {
			self::Object      => ObjectControl::class,
			self::List        => ListControl::class,
			self::Text        => Text::class,
			self::Textarea    => Textarea::class,
			self::Markdown    => Markdown::class,
			self::Link        => Link::class,
			self::Email       => Email::class,
			self::Select      => Select::class,
			self::Radios      => Radios::class,
			self::Checkboxes  => Checkboxes::class,
			self::MultiSelect => MultiSelect::class,
			self::Toggle      => Toggle::class,
			self::Number      => Number::class,
			self::Date        => Date::class,
			self::Image       => Image::class,
			self::Video       => Video::class,
			self::Audio       => Audio::class,
			self::File        => File::class,
			self::Icon        => Icon::class,
			self::Code        => Code::class,
			self::Reference   => Reference::class,
			self::Readonly    => ViewOnly::class
		};
	}
}
