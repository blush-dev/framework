<?php

/**
 * Blush exception marker.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Core;

use Throwable;

/**
 * Marker interface implemented by every exception the framework throws, so a
 * caller can catch any Blush failure in one place with `catch (BlushException
 * $e)`. Each concrete exception still extends the SPL base that best describes
 * its cause (`LogicException`, `RuntimeException`, and so on), and each
 * subsystem adds its own marker or base on top of this one.
 */
interface BlushException extends Throwable
{
}
