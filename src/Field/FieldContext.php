<?php

/**
 * Field context.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

use DateTimeZone;

/**
 * What fields need to know about the site while normalizing and hydrating
 * values. Dates without an offset are read in `$timezone`, and hydrated
 * dates are returned in it (D-045).
 */
final readonly class FieldContext
{
	public function __construct(public DateTimeZone $timezone)
	{}
}
