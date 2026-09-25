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
use Blush\Http\ErrorPages;
use Blush\Http\HttpError;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * Turns an exception thrown further down the pipeline into a 500 response,
 * after logging it. The kernel always runs it outermost, so
 * `Kernel::handle()` returns a response instead of throwing, whether it
 * was called by the front controller, a test, or static export.
 *
 * An `HttpError` (such as the router's `NotFound` and `MethodNotAllowed`)
 * becomes a response with its own status and headers, and isn't logged:
 * it's an answer, not a failure.
 *
 * The site's `ErrorPages` (themed error pages) render the response when
 * they can. The generic `ExceptionRenderer` page is the fallback: when
 * there are no error pages, when they decline (a 500 in debug, which
 * shows the details instead), or when rendering them fails too, which is
 * reported. The generic page is HTML regardless of the SAPI (the kernel
 * serves HTTP even when called from the CLI).
 */
final readonly class HandleErrors implements MiddlewareInterface
{
	public function __construct(
		private ErrorHandler $errors,
		private ExceptionRenderer $renderer,
		private ?ErrorPages $pages = null
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
		} catch (HttpError $error) {
			return $this->respond($error, $error->status, $request, $error->headers);
		} catch (Throwable $exception) {
			$this->errors->report($exception);

			return $this->respond($exception, Status::InternalServerError, $request);
		}
	}

	/**
	 * Returns the error page for an error: the site's own, or else the
	 * generic one.
	 *
	 * @param array<string, string|list<string>> $headers
	 */
	private function respond(Throwable $error, Status $status, ServerRequestInterface $request, array $headers = []): ResponseInterface
	{
		$page = $this->page($error, $status, $request);

		if ($page !== null) {
			foreach ([...$headers, 'Cache-Control' => 'no-store'] as $name => $value) {
				$page = $page->withHeader($name, $value);
			}

			return $page->withStatus($status->value);
		}

		return new Response(
			$status,
			[
				...$headers,
				'Content-Type'  => $this->renderer->contentType(),
				'Cache-Control' => 'no-store'
			],
			$this->renderer->render($error)
		);
	}

	/**
	 * Returns the site's error page, or `null`. A failure while rendering
	 * it is reported, and the generic page is used instead.
	 */
	private function page(Throwable $error, Status $status, ServerRequestInterface $request): ?ResponseInterface
	{
		try {
			return $this->pages?->render($error, $status, $request);
		} catch (Throwable $failure) {
			$this->errors->report($failure);

			return null;
		}
	}
}
