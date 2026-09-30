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

use Blush\Component\Inline\Abbr;
use Blush\Component\Inline\Kbd;
use Blush\Component\Inline\Time;
use Blush\Component\Layout\Figure;
use Blush\Component\Layout\Grid;
use Blush\Component\Layout\Group;
use Blush\Component\Layout\Row;
use Blush\Component\Media\Audio;
use Blush\Component\Media\File;
use Blush\Component\Media\Gallery;
use Blush\Component\Media\Video;

/**
 * The core content components (the "Type enum" of D-019): the ones
 * content can use in any theme (D-033), since the default theme, at the
 * end of every chain, has their templates. They're in the `blush`
 * namespace, and the only components with short names (D-171). Each has a
 * class (D-195) and is seeded into the `ComponentRegistry`.
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
	case Menu     = 'menu';
	case Meter    = 'meter';
	case Progress = 'progress';
	case Row      = 'row';
	case Time     = 'time';
	case Toc      = 'toc';
	case Video    = 'video';

	/**
	 * Returns the component's class.
	 *
	 * @return class-string<Component>
	 */
	public function className(): string
	{
		return match ($this) {
			self::Abbr     => Abbr::class,
			self::Audio    => Audio::class,
			self::Button   => Button::class,
			self::Callout  => Callout::class,
			self::Embed    => Embed::class,
			self::Figure   => Figure::class,
			self::File     => File::class,
			self::Gallery  => Gallery::class,
			self::Grid     => Grid::class,
			self::Group    => Group::class,
			self::Icon     => Icon::class,
			self::Kbd      => Kbd::class,
			self::Menu     => Menu::class,
			self::Meter    => Meter::class,
			self::Progress => Progress::class,
			self::Row      => Row::class,
			self::Time     => Time::class,
			self::Toc      => Toc::class,
			self::Video    => Video::class
		};
	}

	/**
	 * Returns the group the admin's inserter shows it in (D-243).
	 */
	public function category(): ComponentCategory
	{
		return match ($this) {
			self::Abbr, self::Callout, self::Icon, self::Kbd, self::Time          => ComponentCategory::Text,
			self::Audio, self::Embed, self::File, self::Gallery, self::Video      => ComponentCategory::Media,
			self::Figure, self::Grid, self::Group, self::Row                      => ComponentCategory::Layout,
			self::Button, self::Menu, self::Toc                                   => ComponentCategory::Navigation,
			self::Meter, self::Progress                                           => ComponentCategory::Data
		};
	}

	/**
	 * Returns whether it's meant for inside a sentence (`:kbd[Esc]`)
	 * rather than on a line of its own, which the admin's inserter shows
	 * as its kind (D-243).
	 */
	public function isInline(): bool
	{
		return in_array($this, [self::Abbr, self::Icon, self::Kbd, self::Time], true);
	}

	/**
	 * Returns the component's full name.
	 */
	public function componentName(): ComponentName
	{
		return new ComponentName(ComponentName::CORE, $this->value);
	}
}
