<?php

/**
 * Backup frequency.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Backup;

/**
 * How often the schedule backs up the site (D-887).
 */
enum BackupFrequency: string
{
	case Off    = 'off';
	case Daily  = 'daily';
	case Weekly = 'weekly';
}
