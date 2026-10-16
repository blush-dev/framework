<?php

/**
 * Backup kind.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Backup;

/**
 * How a backup came to be (D-886). Only the schedule's own are ever
 * removed by it; the rest stay until someone deletes them.
 */
enum BackupKind: string
{
	case Manual        = 'manual';
	case Scheduled     = 'scheduled';
	case BeforeRestore = 'before-restore';
	case Uploaded      = 'uploaded';
}
