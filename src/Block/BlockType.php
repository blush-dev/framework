<?php

/**
 * Built-in block types.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Block;

use Blush\Block\Inline\Abbr;
use Blush\Block\Inline\Badge;
use Blush\Block\Inline\Cite;
use Blush\Block\Inline\Dfn;
use Blush\Block\Inline\Ins;
use Blush\Block\Inline\Kbd;
use Blush\Block\Inline\Samp;
use Blush\Block\Inline\Small;
use Blush\Block\Inline\Time;
use Blush\Block\Inline\Variable;
use Blush\Block\Layout\Figure;
use Blush\Block\Layout\Grid;
use Blush\Block\Layout\Group;
use Blush\Block\Layout\Row;
use Blush\Block\Layout\Stack;
use Blush\Block\Media\Audio;
use Blush\Block\Media\File;
use Blush\Block\Media\Gallery;
use Blush\Block\Media\Playlist;
use Blush\Block\Media\Video;

/**
 * The core content blocks (the "Type enum" of D-019): the ones
 * content can use in any theme (D-033), since the default theme, at the
 * end of every chain, has their templates. They're in the `core`
 * namespace, and the only blocks with short names (D-171). Each has a
 * class (D-195) and is seeded into the `BlockRegistry`.
 */
enum BlockType: string
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
	case Playlist = 'playlist';
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
	 * Returns the block's class.
	 *
	 * @return class-string<Block>
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
			self::Playlist => Playlist::class,
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
	public function category(): BlockCategory
	{
		return match ($this) {
			self::Abbr, self::Badge, self::Callout, self::Cite, self::Dfn, self::Icon,
			self::Ins, self::Kbd, self::Samp, self::Small, self::Time, self::Var             => BlockCategory::Text,
			self::Audio, self::Embed, self::File, self::Gallery, self::Playlist, self::Video => BlockCategory::Media,
			self::Figure, self::Grid, self::Group, self::Row, self::Stack                    => BlockCategory::Layout,
			self::Button, self::Menu, self::Toc                                              => BlockCategory::Navigation,
			self::Meter, self::Progress                                                      => BlockCategory::Data
		};
	}

	/**
	 * Returns the block's full name.
	 */
	public function blockName(): BlockName
	{
		return new BlockName(BlockName::CORE, $this->value);
	}
}
