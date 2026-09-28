<?php

/**
 * Built-in menu link types.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Menu\Link;

/**
 * The built-in menu link kinds, keyed by the item key that names them
 * (the "Type enum" of D-019).
 */
enum MenuLinkType: string
{
	case Collection = 'collection';
	case Entry      = 'entry';
	case Route      = 'route';
	case Term       = 'term';
	case Url        = 'url';

	/**
	 * Returns the kind's class.
	 *
	 * @return class-string<MenuLink>
	 */
	public function className(): string
	{
		return match ($this) {
			self::Collection => CollectionLink::class,
			self::Entry      => EntryLink::class,
			self::Route      => RouteLink::class,
			self::Term       => TermLink::class,
			self::Url        => UrlLink::class
		};
	}
}
