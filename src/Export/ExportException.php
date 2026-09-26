<?php

/**
 * Export exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export;

use RuntimeException;
use Blush\Core\BlushException;

/**
 * Thrown when an export can't run: another is running, the output
 * folder is unsafe, or a file can't be written.
 */
final class ExportException extends RuntimeException implements BlushException
{
}
