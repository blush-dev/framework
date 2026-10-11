<?php

/**
 * Tool error.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp;

use RuntimeException;
use Blush\Core\BlushException;

/**
 * A tool couldn't do what it was asked (D-753): bad input, nothing there,
 * no permission. Its message goes back to the app as the tool's result,
 * marked as an error, in words the AI can act on, so it can correct
 * itself and try again.
 */
final class ToolError extends RuntimeException implements BlushException
{
}
