<?php

/**
 * Type order.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use Blush\Content\Entry\Position;
use Blush\Content\Query\Order;

/**
 * How a collection's entries are ordered when nothing says otherwise
 * (D-593): newest published first, as posts are, or by `position`, then
 * title, as terms are (D-412). Never by file name (D-516).
 */
enum TypeOrder: string
{
	case Published = 'published';
	case Position  = 'position';

	/**
	 * Returns the order as `Query::orderBy()` takes it.
	 *
	 * @return array{string, Order}
	 */
	public function query(): array
	{
		return match ($this) {
			self::Published => ['published', Order::Desc],
			self::Position  => [Position::FIELD, Order::Asc]
		};
	}
}
