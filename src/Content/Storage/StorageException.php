<?php

/**
 * Content storage exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Storage;

use RuntimeException;
use Blush\Content\ContentException;

/**
 * Thrown when the configured content storage is unknown or can't be built.
 */
final class StorageException extends RuntimeException implements ContentException
{
}
