<?php

/**
 * Backup status.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Backup;

/**
 * Where a backup is: being made, made, or failed (with its reason).
 */
enum BackupStatus: string
{
	case Running = 'running';
	case Done    = 'done';
	case Failed  = 'failed';
}
