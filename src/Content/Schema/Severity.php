<?php

/**
 * Violation severity.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Schema;

/**
 * How serious a schema violation is. `content:lint` fails on errors, shows
 * warnings, and shows notices only with `--strict` (D-081).
 */
enum Severity: string
{
	case Error   = 'error';
	case Warning = 'warning';
	case Notice  = 'notice';
}
