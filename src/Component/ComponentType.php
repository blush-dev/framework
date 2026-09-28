<?php

/**
 * Built-in component types.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component;

use Blush\Content\Schema\Field;
use Blush\Content\Schema\Fields\EnumField;
use Blush\Content\Schema\Fields\MediaField;
use Blush\Content\Schema\Fields\NumberField;
use Blush\Content\Schema\Fields\TextField;
use Blush\Component\Inline\Kbd;
use Blush\Component\Inline\Time;
use Blush\Component\Layout\Grid;
use Blush\Component\Layout\Group;
use Blush\Component\Layout\Row;
use Blush\Component\Media\Audio;
use Blush\Component\Media\File;
use Blush\Component\Media\Video;

/**
 * The core content components (the "Type enum" of D-019): the ones
 * content can use in any theme (D-033), since the default theme, at the
 * end of every chain, has their templates. They're in the `blush`
 * namespace, and the only components with short names (D-171). Each is
 * seeded into the `ComponentRegistry`, most as template-only.
 */
enum ComponentType: string
{
	case Abbr     = 'abbr';
	case Audio    = 'audio';
	case Button   = 'button';
	case Callout  = 'callout';
	case Embed    = 'embed';
	case Figure   = 'figure';
	case File     = 'file';
	case Gallery  = 'gallery';
	case Grid     = 'grid';
	case Group    = 'group';
	case Icon     = 'icon';
	case Kbd      = 'kbd';
	case Meter    = 'meter';
	case Progress = 'progress';
	case Row      = 'row';
	case Time     = 'time';
	case Toc      = 'toc';
	case Video    = 'video';

	/**
	 * Returns the component's class, or `null` for a template-only one.
	 *
	 * @return ?class-string<Component>
	 */
	public function className(): ?string
	{
		return match ($this) {
			self::Audio    => Audio::class,
			self::Button   => Button::class,
			self::Embed    => Embed::class,
			self::File     => File::class,
			self::Grid     => Grid::class,
			self::Group    => Group::class,
			self::Icon     => Icon::class,
			self::Kbd      => Kbd::class,
			self::Meter    => Meter::class,
			self::Progress => Progress::class,
			self::Row      => Row::class,
			self::Time     => Time::class,
			self::Toc      => Toc::class,
			self::Video    => Video::class,
			default     => null
		};
	}

	/**
	 * Returns the component's full name.
	 */
	public function componentName(): ComponentName
	{
		return new ComponentName(ComponentName::CORE, $this->value);
	}

	/**
	 * Returns what a template-only component wraps, or `null` to use its
	 * class's.
	 */
	public function content(): ?ComponentContent
	{
		return match ($this) {
			self::Callout, self::Gallery => ComponentContent::Blocks,
			self::Abbr, self::Figure     => ComponentContent::Text,
			default                      => null
		};
	}

	/**
	 * Returns a template-only component's props, or `null` to read them
	 * from its class.
	 *
	 * @return ?list<Field>
	 */
	public function props(): ?array
	{
		return match ($this) {
			self::Abbr    => [new TextField('title')],
			self::Callout => [new EnumField('tone', ['note', 'info', 'tip', 'warning', 'danger'])->default('note')],
			self::Figure  => [new MediaField('src')->required(), new TextField('alt')],
			self::Gallery => [new NumberField('columns', integer: true, min: 1, max: 6)->default(3)],
			default       => null
		};
	}
}
