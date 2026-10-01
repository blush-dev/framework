<?php

/**
 * Layout tags.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component\Layout;

/**
 * The element a layout component renders as (D-298). `main` is left out
 * because the theme owns it, and `header` and `footer` because inside an
 * entry they'd belong to the theme's `<article>`.
 */
enum LayoutTag: string
{
	case Div     = 'div';
	case Section = 'section';
	case Aside   = 'aside';

	/**
	 * Returns whether the element is a landmark, which its label names.
	 */
	public function isLandmark(): bool
	{
		return $this !== self::Div;
	}
}
