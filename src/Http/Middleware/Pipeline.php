<?php

/**
 * Middleware pipeline.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http\Middleware;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Runs a request through a stack of PSR-15 middleware and then a final
 * handler. The first middleware is the outermost: it sees the request first
 * and the response last. Each middleware gets the rest of the pipeline as
 * its handler, so it can short-circuit by returning its own response.
 */
final readonly class Pipeline implements RequestHandlerInterface
{
	/**
	 * @param list<MiddlewareInterface> $middleware
	 */
	public function __construct(
		private array $middleware,
		private RequestHandlerInterface $handler
	) {
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function handle(ServerRequestInterface $request): ResponseInterface
	{
		$middleware = array_first($this->middleware);

		if ($middleware === null) {
			return $this->handler->handle($request);
		}

		return $middleware->process($request, new self(array_slice($this->middleware, 1), $this->handler));
	}
}
