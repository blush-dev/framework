<?php

/**
 * Backup exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Backup;

use RuntimeException;
use Blush\Core\BlushException;

/**
 * Thrown when a backup can't be made, read, or changed.
 */
final class BackupException extends RuntimeException implements BlushException
{
}
