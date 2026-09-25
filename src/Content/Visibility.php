<?php

/**
 * Entry visibility.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

/**
 * Who can reach a published entry (D-082):
 *
 * - `Public`: routed and listed.
 * - `Unlisted`: routed, but left out of collections, feeds, and sitemaps.
 * - `Hidden`: neither routed nor listed, though a query by name still
 *   finds it. This is 1.x's `hidden`, which a `_`-prefixed file name also
 *   sets (D-078).
 */
enum Visibility: string
{
	case Public   = 'public';
	case Unlisted = 'unlisted';
	case Hidden   = 'hidden';
}
