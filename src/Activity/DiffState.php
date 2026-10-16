<?php

/**
 * Activity diff state.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Activity;

/**
 * Whether a change's before and after can be shown (D-902): they can
 * (`Shown`); it made something new, so there's no before (`New`); what
 * it replaced is no longer kept (`Expired`) or never was, as for a move
 * (`NotKept`); or what it wrote can't be told apart from what came after,
 * since the file changed outside the admin, or is gone (`Unknown`).
 */
enum DiffState: string
{
	case Shown   = 'shown';
	case New     = 'new';
	case Expired = 'expired';
	case NotKept = 'not-kept';
	case Unknown = 'unknown';
}
