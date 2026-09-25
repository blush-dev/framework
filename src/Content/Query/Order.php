<?php

/**
 * Sort order.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Query;

/**
 * The direction a query sorts in.
 */
enum Order: string
{
	case Asc  = 'asc';
	case Desc = 'desc';
}
