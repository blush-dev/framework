<?php

/**
 * Invalid log level exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Log;

use Psr\Log\InvalidArgumentException;
use Blush\Core\BlushException;

/**
 * Thrown when a message is logged at a level PSR-3 doesn't define.
 */
final class InvalidLogLevel extends InvalidArgumentException implements BlushException
{
}
