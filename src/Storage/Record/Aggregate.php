<?php

/**
 * Aggregate.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

/**
 * What a query reduces the records it matches to, over one key: the
 * least or greatest value, or the sum or mean of the numbers.
 */
enum Aggregate: string
{
	case Min = 'min';
	case Max = 'max';
	case Sum = 'sum';
	case Avg = 'avg';
}
