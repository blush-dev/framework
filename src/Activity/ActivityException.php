<?php

/**
 * Activity log exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Activity;

use RuntimeException;
use Blush\Core\BlushException;

/**
 * The activity log couldn't be read or written, or a change can't be
 * undone.
 */
final class ActivityException extends RuntimeException implements BlushException
{
}
