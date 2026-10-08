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

namespace Blush\Storage\Record;

/**
 * The direction a query sorts in: records (D-643) and entries alike.
 */
enum Order: string
{
	case Asc  = 'asc';
	case Desc = 'desc';
}
