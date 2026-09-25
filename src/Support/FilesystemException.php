<?php

/**
 * Filesystem exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Support;

use RuntimeException;
use Blush\Core\BlushException;

/**
 * Thrown when a filesystem operation fails or a path escapes its root.
 */
final class FilesystemException extends RuntimeException implements BlushException
{
}
