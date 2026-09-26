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
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Blush\Container\Attributes\TaggedAbstracts;
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
 * The request runs through `HandleErrors`, then the framework's and
 * extensions' middleware (tagged `Kernel::MIDDLEWARE`, in tag order:
 * `ConditionalGet`, then the page cache), then the site's global
 * middleware from `HttpConfig`, then the application's handler: the
 * router (`RoutingServiceProvider` binds it). Middleware are resolved
 * through the container per request. `RequestReceived` and
 * `ResponseReady` are dispatched around the pipeline.
 */
final readonly class Kernel implements RequestHandlerInterface
{
	/**
	 * The tag for middleware that runs before the site's own.
	 */
	public const string MIDDLEWARE = 'http.middleware';

	/**
	 * @param array<mixed, class-string<MiddlewareInterface>> $middleware Tagged middleware classes.
	 */
	public function __construct(
		private ServiceResolver $resolver,
		private HttpConfig $config,
		private RequestHandlerInterface $handler,
		private Dispatcher $events,
		#[TaggedAbstracts(self::MIDDLEWARE)] private array $middleware = []
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
			[HandleErrors::class, ...array_values($this->middleware), ...$this->config->middleware]
		);

		$response = new Pipeline($middleware, $this->handler)->handle($request);

		$this->events->dispatch(new ResponseReady($request, $response));

		return $response;
	}
}
