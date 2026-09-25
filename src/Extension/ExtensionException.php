<?php

/**
 * Extension exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

use RuntimeException;
use Blush\Core\BlushException;

/**
 * Thrown when an extension manifest is missing or invalid, two extensions
 * share a name, or config enables an extension that doesn't exist.
 */
final class ExtensionException extends RuntimeException implements BlushException
{
}
