<?php

/**
 * Media exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use RuntimeException;
use Blush\Core\BlushException;

/**
 * Thrown when media metadata can't be written.
 */
final class MediaException extends RuntimeException implements BlushException
{
}
