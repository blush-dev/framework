<?php

/**
 * Error-handling middleware.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http\Middleware;

use Override;
use Throwable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Blush\Error\ErrorHandler;
use Blush\Error\ExceptionRenderer;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * Turns an exception thrown further down the pipeline into a 500 response,
 * after logging it. The kernel always runs it outermost, so
 * `Kernel::handle()` returns a response instead of throwing, whether it
 * was called by the front controller, a test, or static export.
 *
 * It renders HTML regardless of the SAPI (the kernel serves HTTP even when
 * called from the CLI). Themed error pages replace the generic page in M5,
 * and the router's not-found and method-not-allowed errors map to their own
 * statuses in M3.
 */
final readonly class HandleErrors implements MiddlewareInterface
{
	public function __construct(
		private ErrorHandler $errors,
		private ExceptionRenderer $renderer
	) {
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		try {
			return $handler->handle($request);
		} catch (Throwable $exception) {
			$this->errors->report($exception);

			return new Response(
				Status::InternalServerError,
				[
					'Content-Type'  => $this->renderer->contentType(),
					'Cache-Control' => 'no-store'
				],
				$this->renderer->render($exception)
			);
		}
	}
}
