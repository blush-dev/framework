<?php

/**
 * Invalid frequency.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

use InvalidArgumentException;
use Blush\Core\BlushException;

/**
 * A scheduled task's frequency isn't a cron expression Blush can read.
 */
final class InvalidFrequency extends InvalidArgumentException implements BlushException
{
}
