<?php

/**
 * Response ready event.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http\Events;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Dispatched by the kernel once the response is built, before it's
 * returned for emitting (or to whoever called the kernel). Listeners
 * observe; middleware is how a response is changed.
 */
final readonly class ResponseReady
{
	public function __construct(
		public ServerRequestInterface $request,
		public ResponseInterface $response
	) {
	}
}
