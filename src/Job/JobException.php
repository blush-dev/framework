<?php

/**
 * Job exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

use RuntimeException;
use Blush\Core\BlushException;

/**
 * A job couldn't be queued, found, or kept.
 */
class JobException extends RuntimeException implements BlushException
{
}
