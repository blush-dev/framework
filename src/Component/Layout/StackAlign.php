<?php

/**
 * Stack alignment.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component\Layout;

/**
 * How a `stack` lines its items up across the column.
 */
enum StackAlign: string
{
	case Stretch = 'stretch';
	case Start   = 'start';
	case Center  = 'center';
	case End     = 'end';

	/**
	 * Returns the `align-items` value.
	 */
	public function css(): string
	{
		return match ($this) {
			self::Start => 'flex-start',
			self::End   => 'flex-end',
			default     => $this->value
		};
	}
}
