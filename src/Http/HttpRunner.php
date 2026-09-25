<?php

/**
 * HTTP runner.
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
use Blush\Core\Runner;
use Blush\Error\ErrorHandler;
use Blush\Error\HtmlRenderer;

/**
 * Runs a web request. The front controller is just:
 *
 *     require '/path/to/project/vendor/autoload.php';
 *
 *     new Blush\Http\HttpRunner('/path/to/project')->run();
 *
 * The project path is the one line to change when the web root lives
 * somewhere else, such as a host's fixed `public_html` (D-046).
 */
final class HttpRunner extends Runner
{
	/**
	 * Handles the current request and sends the response.
	 */
	public function run(): void
	{
		$request = RequestFactory::fromGlobals();

		$this->application()->container()->make(Emitter::class)->emit(
			$this->handle($request),
			withBody: $request->getMethod() !== 'HEAD'
		);
	}

	/**
	 * Handles a request through the kernel.
	 */
	public function handle(ServerRequestInterface $request): ResponseInterface
	{
		return $this->application()->container()->make(Kernel::class)->handle($request);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function earlyErrorHandler(bool $debug): ErrorHandler
	{
		return new ErrorHandler(new HtmlRenderer($debug));
	}
}
