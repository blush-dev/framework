<?php

/**
 * Env exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Env;

use RuntimeException;
use Blush\Core\BlushException;

/**
 * Thrown when a `.env` file can't be parsed, or a variable is missing or has
 * a value of the wrong type.
 */
final class EnvException extends RuntimeException implements BlushException
{
}
