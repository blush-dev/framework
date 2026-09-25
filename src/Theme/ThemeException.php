<?php

/**
 * Theme exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

use RuntimeException;
use Blush\Core\BlushException;

/**
 * Thrown when a theme is missing, its manifest is invalid, or its parent
 * chain is broken.
 */
final class ThemeException extends RuntimeException implements BlushException
{
}
