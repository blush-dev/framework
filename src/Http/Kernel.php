<?php

/**
 * HTTP kernel.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Blush\Container\ServiceResolver;
use Blush\Event\Dispatcher;
use Blush\Http\Events\RequestReceived;
use Blush\Http\Events\ResponseReady;
use Blush\Http\Middleware\HandleErrors;
use Blush\Http\Middleware\Pipeline;

/**
 * Turns a request into a response: the one entry point for serving HTTP.
 * The web front controller, tests, the CLI, static export, and admin
 * previews all call `handle()`, and nothing in it reads PHP's globals.
 *
 * The request runs through `HandleErrors`, then the configured global
 * middleware (resolved through the container per request), then the
 * application's handler. Until the router arrives (M3), the handler is
 * `WelcomeHandler`. `RequestReceived` and `ResponseReady` are dispatched
 * around the pipeline.
 */
final readonly class Kernel implements RequestHandlerInterface
{
	public function __construct(
		private ServiceResolver $resolver,
		private HttpConfig $config,
		private RequestHandlerInterface $handler,
		private Dispatcher $events
	) {
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function handle(ServerRequestInterface $request): ResponseInterface
	{
		$this->events->dispatch(new RequestReceived($request));

		$middleware = array_map(
			$this->resolver->make(...),
			[HandleErrors::class, ...$this->config->middleware]
		);

		$response = new Pipeline($middleware, $this->handler)->handle($request);

		$this->events->dispatch(new ResponseReady($request, $response));

		return $response;
	}
}
