<?php

/**
 * Built-in region item types.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Region\Item;

/**
 * The built-in region item kinds, keyed by the item key that names them
 * (the "Type enum" of D-019).
 */
enum RegionItemType: string
{
	case Component = 'component';
	case Entry     = 'entry';
	case Markdown  = 'markdown';
	case View      = 'view';

	/**
	 * Returns the kind's class.
	 *
	 * @return class-string<RegionItem>
	 */
	public function className(): string
	{
		return match ($this) {
			self::Component => ComponentItem::class,
			self::Entry     => EntryItem::class,
			self::Markdown  => MarkdownItem::class,
			self::View      => ViewItem::class
		};
	}
}
