<?php

/**
 * Markdown exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown;

use RuntimeException;
use Blush\Core\BlushException;

/**
 * Thrown when Markdown can't be converted, or when the converter can't be
 * built from its config.
 */
final class MarkdownException extends RuntimeException implements BlushException
{
}
