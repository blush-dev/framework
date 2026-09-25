<?php

/**
 * Emitter tests.
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
use Blush\Http\Emitter;
use Blush\Http\EmitterException;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Tests\Fixtures\Http\RecordingSapi;

#[CoversClass(Emitter::class)]
final class EmitterTest extends TestCase
{
	public function testEmitsStatusHeadersAndBody(): void
	{
		$sapi     = new RecordingSapi();
		$response = Response::html(str_repeat('x', 20), Status::Created)
			->withAddedHeader('Set-Cookie', 'a=1')
			->withAddedHeader('Set-Cookie', 'b=2');

		new Emitter($sapi, chunkSize: 8)->emit($response);

		$this->assertSame([
			['line' => 'HTTP/1.1 201 Created', 'replace' => true, 'status' => 201],
			['line' => 'Content-Type: text/html; charset=UTF-8', 'replace' => true, 'status' => 0],
			['line' => 'Set-Cookie: a=1', 'replace' => true, 'status' => 0],
			['line' => 'Set-Cookie: b=2', 'replace' => false, 'status' => 0]
		], $sapi->headers);
		$this->assertSame(str_repeat('x', 20), $sapi->body);
		$this->assertTrue($sapi->finished);
	}

	public function testHeadRequestsAndEmptyStatusesHaveNoBody(): void
	{
		$head = new RecordingSapi();
		new Emitter($head)->emit(Response::text('body'), withBody: false);

		$this->assertSame('', $head->body);
		$this->assertSame('HTTP/1.1 200 OK', $head->headers[0]['line']);

		$notModified = new RecordingSapi();
		new Emitter($notModified)->emit(Response::text('body')->withStatus(304));

		$this->assertSame('', $notModified->body);
	}

	public function testUnregisteredStatusesHaveNoReasonPhrase(): void
	{
		$sapi = new RecordingSapi();
		new Emitter($sapi)->emit(new Response(299));

		$this->assertSame('HTTP/1.1 299', $sapi->headers[0]['line']);
	}

	public function testRefusesOnceOutputHasStarted(): void
	{
		$this->expectException(EmitterException::class);

		new Emitter(new RecordingSapi(sent: true))->emit(new Response());
	}
}
