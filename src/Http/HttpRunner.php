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
use Throwable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Core\Runner;
use Blush\Error\ErrorHandler;
use Blush\Error\HtmlRenderer;
use Blush\Setup\SetupChecks;
use Blush\Setup\SetupPage;

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
	 * Handles the current request and sends the response. Anything
	 * printed along the way (a `dump()`, say) is buffered and added to the
	 * top of the response body, so it can't send headers early and break
	 * the response (see `StrayOutput`).
	 *
	 * @throws Throwable Whatever escapes the kernel, after printing the
	 *                   buffered output.
	 */
	public function run(): void
	{
		$request = RequestFactory::fromGlobals();
		$level   = ob_get_level();

		ob_start();

		try {
			$response = $this->handle($request);
		} catch (Throwable $exception) {
			echo self::endBuffers($level);
			throw $exception;
		}

		$this->application()->container()->make(Emitter::class)->emit(
			StrayOutput::insert($response, self::endBuffers($level)),
			withBody: $request->getMethod() !== 'HEAD'
		);
	}

	/**
	 * Handles a request through the kernel. While storage isn't writable,
	 * every request gets the setup page instead, before the application
	 * loads (D-218).
	 */
	public function handle(ServerRequestInterface $request): ResponseInterface
	{
		$failures = SetupChecks::failures(new SetupChecks($this->sitePaths())->storage());

		if ($failures !== []) {
			return SetupPage::response($failures);
		}

		return $this->application()->container()->make(Kernel::class)->handle($request);
	}

	/**
	 * Closes the output buffers opened since `$level` and returns what
	 * they held, outermost first.
	 */
	private static function endBuffers(int $level): string
	{
		$output = '';

		while (ob_get_level() > $level) {
			$output = (string) ob_get_clean() . $output;
		}

		return $output;
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
