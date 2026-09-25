<?php

/**
 * Middleware pipeline tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Error\ErrorHandler;
use Blush\Error\HtmlRenderer;
use Blush\Http\Middleware\HandleErrors;
use Blush\Http\Middleware\Pipeline;
use Blush\Http\Request;
use Blush\Tests\Fixtures\Http\AddHeader;
use Blush\Tests\Fixtures\Http\EchoHandler;
use Blush\Tests\Fixtures\Http\Failing;
use Blush\Tests\Fixtures\Http\ShortCircuit;
use Blush\Tests\Fixtures\Log\MemoryLogger;

#[CoversClass(Pipeline::class)]
#[CoversClass(HandleErrors::class)]
final class PipelineTest extends TestCase
{
	public function testRunsMiddlewareOutermostFirst(): void
	{
		$pipeline = new Pipeline([new AddHeader('outer'), new AddHeader('inner')], new EchoHandler());
		$response = $pipeline->handle(Request::create('/path'));

		$this->assertSame('GET /path [outer,inner]', (string) $response->getBody());
		$this->assertSame(['inner', 'outer'], $response->getHeader('X-Trace'));
	}

	public function testWithoutMiddlewareCallsTheHandler(): void
	{
		$response = new Pipeline([], new EchoHandler())->handle(Request::create('/', 'PUT'));

		$this->assertSame('PUT / []', (string) $response->getBody());
	}

	public function testMiddlewareCanShortCircuit(): void
	{
		$pipeline = new Pipeline([new AddHeader(), new ShortCircuit(), new AddHeader('never')], new EchoHandler());
		$response = $pipeline->handle(Request::create('/'));

		$this->assertSame(403, $response->getStatusCode());
		$this->assertSame(['outer'], $response->getHeader('X-Trace'));
	}

	public function testHandleErrorsTurnsExceptionsIntoResponses(): void
	{
		$logger   = new MemoryLogger();
		$renderer = new HtmlRenderer(debug: true);
		$pipeline = new Pipeline(
			[new HandleErrors(new ErrorHandler($renderer, $logger), $renderer), new Failing()],
			new EchoHandler()
		);

		$response = $pipeline->handle(Request::create('/'));

		$this->assertSame(500, $response->getStatusCode());
		$this->assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
		$this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
		$this->assertStringContainsString('Middleware exploded &lt;b&gt;here&lt;/b&gt;.', (string) $response->getBody());
		$this->assertCount(1, $logger->records);
	}
}
