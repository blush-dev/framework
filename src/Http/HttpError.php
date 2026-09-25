<?php

/**
 * HTTP error.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

use RuntimeException;
use Throwable;

/**
 * An error the client should see as an HTTP status (a 4xx or 5xx), such as
 * a missing page. Anything may throw one: the router, a controller, or
 * middleware. The kernel's `HandleErrors` turns it into a response with its
 * status and headers, without logging it as a server error.
 *
 * Throw it directly for any status, or throw a subclass such as `NotFound`.
 */
class HttpError extends RuntimeException implements HttpException
{
	/**
	 * @param array<string, string> $headers Headers the response must carry (such as `Allow`).
	 */
	public function __construct(
		public readonly Status $status,
		string $message = '',
		public readonly array $headers = [],
		?Throwable $previous = null
	) {
		parent::__construct($message === '' ? $status->reasonPhrase() : $message, $status->value, $previous);
	}
}
