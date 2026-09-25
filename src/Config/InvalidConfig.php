<?php

/**
 * Invalid config exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Config;

use InvalidArgumentException;
use Blush\Core\BlushException;

/**
 * Thrown when configuration is missing, malformed, or has an invalid value.
 */
final class InvalidConfig extends InvalidArgumentException implements BlushException
{
}
