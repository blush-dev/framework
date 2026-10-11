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

namespace Blush\Tool;

use RuntimeException;
use Blush\Core\BlushException;

/**
 * A tool couldn't do what it was asked (D-753): bad input, nothing there,
 * no permission. Its message goes back as the tool's result, marked as an
 * error, in words people and AIs can act on, so they can correct it and
 * try again, with optional details, a line each.
 */
final class ToolError extends RuntimeException implements BlushException
{
	/**
	 * @param list<string> $details
	 */
	public function __construct(string $message, public readonly array $details = [])
	{
		parent::__construct($message);
	}
}
