<?php

/**
 * Method not allowed error.
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
 * Thrown when a route matches the URL but not the request method. The
 * response lists the methods the URL does accept in its `Allow` header.
 */
final class MethodNotAllowed extends HttpError
{
	/**
	 * @param list<string> $allowed The methods the URL accepts.
	 */
	public function __construct(public readonly array $allowed, ?Throwable $previous = null)
	{
		parent::__construct(
			Status::MethodNotAllowed,
			headers: ['Allow' => implode(', ', $allowed)],
			previous: $previous
		);
	}
}
