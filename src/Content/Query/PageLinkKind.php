<?php

/**
 * Page link kinds.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Query;

/**
 * What a `PageLink` stands for. The values are 1.x's pagination class
 * suffixes (`pagination__item--dots`), so themes can use them in class
 * names as they are.
 */
enum PageLinkKind: string
{
	/**
	 * The link to the previous page.
	 */
	case Previous = 'prev';

	/**
	 * A numbered link to another page.
	 */
	case Number = 'number';

	/**
	 * The page being viewed (no URL).
	 */
	case Current = 'current';

	/**
	 * A gap standing for the pages left out (no number or URL).
	 */
	case Dots = 'dots';

	/**
	 * The link to the next page.
	 */
	case Next = 'next';
}
