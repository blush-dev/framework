<?php

/**
 * Storage exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage;

use RuntimeException;
use Blush\Core\BlushException;

/**
 * Thrown when the configured storage is unknown or can't be built.
 */
final class StorageException extends RuntimeException implements BlushException
{
}
