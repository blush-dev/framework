<?php

/**
 * View exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

use RuntimeException;
use Blush\Core\BlushException;

/**
 * Thrown when a view can't be found or rendered.
 */
class ViewException extends RuntimeException implements BlushException
{
}
