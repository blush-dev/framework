<?php

/**
 * Invalid environment exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Core;

use InvalidArgumentException;

/**
 * Thrown when `APP_ENV` names an environment Blush doesn't know.
 */
final class InvalidEnvironment extends InvalidArgumentException implements BlushException
{
}
