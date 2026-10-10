<?php

/**
 * Secret exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Secret;

use RuntimeException;
use Blush\Core\BlushException;

/**
 * A secret that can't be read, stored, or named as asked (D-715). Its
 * message never holds a secret's value.
 */
abstract class SecretException extends RuntimeException implements BlushException
{
}
