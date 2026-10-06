<?php

/**
 * Built-in directive types.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Directive;

use Blush\Directive\Inline\Abbr;
use Blush\Directive\Inline\Badge;
use Blush\Directive\Inline\Cite;
use Blush\Directive\Inline\Dfn;
use Blush\Directive\Inline\Ins;
use Blush\Directive\Inline\Kbd;
use Blush\Directive\Inline\Samp;
use Blush\Directive\Inline\Small;
use Blush\Directive\Inline\Time;
use Blush\Directive\Inline\Variable;
use Blush\Directive\Layout\Figure;
use Blush\Directive\Layout\Grid;
use Blush\Directive\Layout\Group;
use Blush\Directive\Layout\Row;
use Blush\Directive\Layout\Stack;
use Blush\Directive\Media\Audio;
use Blush\Directive\Media\File;
use Blush\Directive\Media\Gallery;
use Blush\Directive\Media\Video;

/**
 * The core content directives (the "Type enum" of D-019): the ones
 * content can use in any theme (D-033), since the default theme, at the
 * end of every chain, has their templates. They're in the `blush`
 * namespace, and the only directives with short names (D-171). Each has a
 * class (D-195) and is seeded into the `DirectiveRegistry`.
 */
enum DirectiveType: string
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
	 * Returns the directive's class.
	 *
	 * @return class-string<Directive>
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
	public function category(): DirectiveCategory
	{
		return match ($this) {
			self::Abbr, self::Badge, self::Callout, self::Cite, self::Dfn, self::Icon,
			self::Ins, self::Kbd, self::Samp, self::Small, self::Time, self::Var         => DirectiveCategory::Text,
			self::Audio, self::Embed, self::File, self::Gallery, self::Video            => DirectiveCategory::Media,
			self::Figure, self::Grid, self::Group, self::Row, self::Stack              => DirectiveCategory::Layout,
			self::Button, self::Menu, self::Toc                                         => DirectiveCategory::Navigation,
			self::Meter, self::Progress                                                 => DirectiveCategory::Data
		};
	}

	/**
	 * Returns the directive's full name.
	 */
	public function directiveName(): DirectiveName
	{
		return new DirectiveName(DirectiveName::CORE, $this->value);
	}
}
