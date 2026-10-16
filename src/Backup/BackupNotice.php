<?php

/**
 * Backup notice.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Backup;

/**
 * When a scheduled backup sends an email (D-887): only when one fails
 * (and once more when they work again), after every one, or never.
 */
enum BackupNotice: string
{
	case Failures = 'failures';
	case Always   = 'always';
	case Never    = 'never';
}
