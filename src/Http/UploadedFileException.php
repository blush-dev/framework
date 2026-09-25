<?php

/**
 * Uploaded file exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

use RuntimeException;

/**
 * Thrown when an uploaded file can't be read or moved, or was already moved.
 */
final class UploadedFileException extends RuntimeException implements HttpException
{
}
