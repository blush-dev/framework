<?php

/**
 * MCP exception.
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
 * The MCP server couldn't do something: a tool that can't be built.
 */
final class McpException extends RuntimeException implements BlushException
{
}
