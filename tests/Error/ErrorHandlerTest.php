<?php

/**
 * Error handler tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Error;

use ErrorException;
use LogicException;
use RuntimeException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Error\ErrorHandler;
use Blush\Error\FatalError;
use Blush\Error\HtmlRenderer;
use Blush\Error\TextRenderer;
use Blush\Tests\Fixtures\Log\MemoryLogger;

#[CoversClass(ErrorHandler::class)]
#[CoversClass(FatalError::class)]
#[CoversClass(HtmlRenderer::class)]
#[CoversClass(TextRenderer::class)]
final class ErrorHandlerTest extends TestCase
{
	private MemoryLogger $logger;

	private string $output = '';

	private int $reporting = 0;

	/**
	 * PHPUnit lowers `error_reporting` while its own handler is active;
	 * these tests need the full level the handler sees in production.
	 */
	protected function setUp(): void
	{
		$this->reporting = error_reporting(E_ALL);
		$this->logger    = new MemoryLogger();
	}

	protected function tearDown(): void
	{
		error_reporting($this->reporting);
	}

	private function handler(bool $debug = false): ErrorHandler
	{
		return new ErrorHandler(new TextRenderer($debug), $this->logger, function (string $text): void {
			$this->output .= $text;
		});
	}

	public function testConvertsWarningsToExceptions(): void
	{
		$this->expectException(ErrorException::class);
		$this->expectExceptionMessage('Something odd');

		$this->handler()->handleError(E_USER_WARNING, 'Something odd', __FILE__, __LINE__);
	}

	public function testLogsDeprecationsInsteadOfThrowing(): void
	{
		$this->assertTrue($this->handler()->handleError(E_USER_DEPRECATED, 'Old thing', 'file.php', 3));
		$this->assertSame('notice', $this->logger->records[0]['level']);
		$this->assertSame('Old thing', $this->logger->records[0]['context']['message']);
	}

	public function testIgnoresErrorsExcludedByErrorReporting(): void
	{
		$previous = error_reporting(E_ALL & ~E_USER_NOTICE);

		try {
			$this->assertFalse($this->handler()->handleError(E_USER_NOTICE, 'Quiet', 'file.php', 1));
		} finally {
			error_reporting($previous);
		}
	}

	public function testRegisteredHandlerThrowsForRealWarnings(): void
	{
		$handler = $this->handler();
		$handler->register();

		try {
			$this->expectException(ErrorException::class);

			trigger_error('Registered', E_USER_WARNING);
		} finally {
			$handler->unregister();
		}
	}

	public function testHandleExceptionLogsAndRenders(): void
	{
		$this->handler()->handleException(new RuntimeException('Boom', 0, new LogicException('Root')));

		$this->assertSame('critical', $this->logger->records[0]['level']);
		$this->assertSame(RuntimeException::class, $this->logger->records[0]['context']['class']);
		$this->assertStringContainsString("RuntimeException: Boom\n", $this->output);
		$this->assertStringContainsString('Caused by LogicException: Root', $this->output);
		$this->assertStringNotContainsString('#0 ', $this->output);
	}

	public function testDebugTextIncludesTraces(): void
	{
		$this->handler(debug: true)->handleException(new RuntimeException('Boom'));

		$this->assertStringContainsString('#0 ', $this->output);
	}

	public function testFatalErrorKeepsTheBacktrace(): void
	{
		$error = FatalError::fromLastError([
			'type'    => E_ERROR,
			'message' => 'Allowed memory size exhausted',
			'file'    => '/site/src/Big.php',
			'line'    => 12,
			'trace'   => [
				['file' => '/site/src/Big.php', 'line' => 12, 'function' => 'load', 'class' => 'Big', 'type' => '->'],
				['function' => '{main}']
			]
		]);

		$this->assertSame(E_ERROR, $error->getSeverity());
		$this->assertSame(
			"#0 /site/src/Big.php:12: Big->load()\n#1 [internal function]: {main}()",
			$error->fatalTraceAsString()
		);

		$this->handler()->handleException($error);

		$this->assertSame($error->fatalTraceAsString(), $this->logger->records[0]['context']['trace']);
	}

	public function testHtmlRendererHidesDetailsUnlessDebugging(): void
	{
		$exception = new RuntimeException('<secret>');

		$plain = new HtmlRenderer()->render($exception);
		$debug = new HtmlRenderer(true)->render($exception);

		$this->assertStringContainsString('Something went wrong', $plain);
		$this->assertStringNotContainsString('secret', $plain);
		$this->assertStringContainsString('&lt;secret&gt;', $debug);
		$this->assertStringNotContainsString('<secret>', $debug);
		$this->assertSame('text/html; charset=UTF-8', new HtmlRenderer()->contentType());
	}
}
