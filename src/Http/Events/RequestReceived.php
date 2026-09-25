<?php

/**
 * Request received event.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http\Events;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Dispatched by the kernel when a request arrives, before any middleware
 * runs. Listeners observe; middleware is how a request is changed.
 */
final readonly class RequestReceived
{
	public function __construct(public ServerRequestInterface $request)
	{
	}
}
