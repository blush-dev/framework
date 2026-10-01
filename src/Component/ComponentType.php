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
use Blush\Component\Inline\Badge;
use Blush\Component\Inline\Cite;
use Blush\Component\Inline\Dfn;
use Blush\Component\Inline\Ins;
use Blush\Component\Inline\Kbd;
use Blush\Component\Inline\Samp;
use Blush\Component\Inline\Small;
use Blush\Component\Inline\Time;
use Blush\Component\Inline\Variable;
use Blush\Component\Layout\Figure;
use Blush\Component\Layout\Grid;
use Blush\Component\Layout\Group;
use Blush\Component\Layout\Row;
use Blush\Component\Layout\Stack;
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
	case Badge    = 'badge';
	case Button   = 'button';
	case Callout  = 'callout';
	case Cite     = 'cite';
	case Dfn      = 'dfn';
	case Embed    = 'embed';
	case Figure   = 'figure';
	case File     = 'file';
	case Gallery  = 'gallery';
	case Grid     = 'grid';
	case Group    = 'group';
	case Icon     = 'icon';
	case Ins      = 'ins';
	case Kbd      = 'kbd';
	case Menu     = 'menu';
	case Meter    = 'meter';
	case Progress = 'progress';
	case Row      = 'row';
	case Samp     = 'samp';
	case Small    = 'small';
	case Stack    = 'stack';
	case Time     = 'time';
	case Toc      = 'toc';
	case Var      = 'var';
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
			self::Badge    => Badge::class,
			self::Button   => Button::class,
			self::Callout  => Callout::class,
			self::Cite     => Cite::class,
			self::Dfn      => Dfn::class,
			self::Embed    => Embed::class,
			self::Figure   => Figure::class,
			self::File     => File::class,
			self::Gallery  => Gallery::class,
			self::Grid     => Grid::class,
			self::Group    => Group::class,
			self::Icon     => Icon::class,
			self::Ins      => Ins::class,
			self::Kbd      => Kbd::class,
			self::Menu     => Menu::class,
			self::Meter    => Meter::class,
			self::Progress => Progress::class,
			self::Row      => Row::class,
			self::Samp     => Samp::class,
			self::Small    => Small::class,
			self::Stack    => Stack::class,
			self::Time     => Time::class,
			self::Toc      => Toc::class,
			self::Var      => Variable::class,
			self::Video    => Video::class
		};
	}

	/**
	 * Returns the group the admin's inserter shows it in (D-243).
	 */
	public function category(): ComponentCategory
	{
		return match ($this) {
			self::Abbr, self::Badge, self::Callout, self::Cite, self::Dfn, self::Icon,
			self::Ins, self::Kbd, self::Samp, self::Small, self::Time, self::Var         => ComponentCategory::Text,
			self::Audio, self::Embed, self::File, self::Gallery, self::Video            => ComponentCategory::Media,
			self::Figure, self::Grid, self::Group, self::Row, self::Stack              => ComponentCategory::Layout,
			self::Button, self::Menu, self::Toc                                         => ComponentCategory::Navigation,
			self::Meter, self::Progress                                                 => ComponentCategory::Data
		};
	}

	/**
	 * Returns whether it's meant for inside a sentence (`:kbd[Esc]`)
	 * rather than on a line of its own, which the admin's inserter shows
	 * as its kind (D-243).
	 */
	public function isInline(): bool
	{
		return in_array($this, [
			self::Abbr,
			self::Badge,
			self::Cite,
			self::Dfn,
			self::Icon,
			self::Ins,
			self::Kbd,
			self::Samp,
			self::Small,
			self::Time,
			self::Var
		], true);
	}

	/**
	 * Returns the component's full name.
	 */
	public function componentName(): ComponentName
	{
		return new ComponentName(ComponentName::CORE, $this->value);
	}
}
