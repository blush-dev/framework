<?php

/**
 * Notification exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Notification;

use RuntimeException;
use Blush\Core\BlushException;

/**
 * A notification couldn't be found, built, or queued.
 */
class NotificationException extends RuntimeException implements BlushException
{
}
