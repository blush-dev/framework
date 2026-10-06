<?php

/**
 * Row justification.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Directive\Layout;

/**
 * How a `row` spreads its items along the line.
 */
enum RowJustify: string
{
	case Start   = 'start';
	case Center  = 'center';
	case End     = 'end';
	case Between = 'between';

	/**
	 * Returns the `justify-content` value.
	 */
	public function css(): string
	{
		return match ($this) {
			self::Start   => 'flex-start',
			self::Center  => 'center',
			self::End     => 'flex-end',
			self::Between => 'space-between'
		};
	}
}
