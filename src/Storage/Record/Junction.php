<?php

/**
 * Junction.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

/**
 * How a group's conditions combine.
 */
enum Junction: string
{
	/**
	 * Every condition holds.
	 */
	case All = 'all';

	/**
	 * At least one holds.
	 */
	case Any = 'any';
}
