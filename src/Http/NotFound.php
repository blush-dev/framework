<?php

/**
 * Not found error.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

use Throwable;

/**
 * Thrown when nothing answers a request's URL: no route matches, or a
 * controller can't find what the route points to. The router checks its
 * redirects before a 404 is returned.
 */
final class NotFound extends HttpError
{
	public function __construct(string $message = '', ?Throwable $previous = null)
	{
		parent::__construct(Status::NotFound, $message, previous: $previous);
	}
}
