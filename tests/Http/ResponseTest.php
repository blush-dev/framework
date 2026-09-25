<?php

/**
 * Response tests.
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
use Blush\Http\InvalidMessage;
use Blush\Http\LimitedStream;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Http\Stream;
use Blush\Http\StreamException;
use Blush\Http\Uri;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(Response::class)]
#[CoversClass(Status::class)]
#[CoversClass(LimitedStream::class)]
final class ResponseTest extends TestCase
{
	use TemporaryDirectory;

	public function testDefaults(): void
	{
		$response = new Response();

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame('OK', $response->getReasonPhrase());
		$this->assertSame(Status::Ok, $response->status());
		$this->assertSame('', (string) $response->getBody());
	}

	public function testStatusAndReasonPhrase(): void
	{
		$response = new Response()->withStatus(418);

		$this->assertSame(418, $response->getStatusCode());
		$this->assertSame('', $response->getReasonPhrase());
		$this->assertNull($response->status());
		$this->assertSame("I'm a teapot", $response->withStatus(418, "I'm a teapot")->getReasonPhrase());
		$this->assertSame('Not Found', $response->withStatus(404)->getReasonPhrase());

		$this->expectException(InvalidMessage::class);
		(void) $response->withStatus(600);
	}

	public function testNamedConstructors(): void
	{
		$html = Response::html('<p>Hi</p>', Status::NotFound, ['X-Test' => 'yes']);

		$this->assertSame(404, $html->getStatusCode());
		$this->assertSame('text/html; charset=UTF-8', $html->getHeaderLine('Content-Type'));
		$this->assertSame('yes', $html->getHeaderLine('X-Test'));
		$this->assertSame('<p>Hi</p>', (string) $html->getBody());

		$this->assertSame('text/plain; charset=UTF-8', Response::text('hi')->getHeaderLine('Content-Type'));
		$this->assertSame('application/rss+xml; charset=UTF-8', Response::xml('<rss/>', contentType: 'application/rss+xml')->getHeaderLine('Content-Type'));

		$json = Response::json(['url' => 'https://example.com/é']);

		$this->assertSame('application/json', $json->getHeaderLine('Content-Type'));
		$this->assertSame('{"url":"https://example.com/é"}', (string) $json->getBody());

		$this->assertSame(304, Response::notModified(['ETag' => '"abc"'])->getStatusCode());
	}

	public function testJsonEncodingFailuresThrow(): void
	{
		$this->expectException(InvalidMessage::class);

		Response::json(NAN);
	}

	public function testRedirects(): void
	{
		$redirect = Response::redirect(new Uri('https://example.com/new'), Status::MovedPermanently);

		$this->assertSame(301, $redirect->getStatusCode());
		$this->assertSame('https://example.com/new', $redirect->getHeaderLine('Location'));
		$this->assertSame(302, Response::redirect('/elsewhere')->getStatusCode());
		$this->assertTrue(Status::Found->isRedirect());

		$this->expectException(InvalidMessage::class);
		Response::redirect('/nope', 200);
	}

	public function testFileResponses(): void
	{
		$path     = $this->writeTemporaryFile('note.txt', 'plain text');
		$response = Response::file($path);

		$this->assertSame('text/plain', $response->getHeaderLine('Content-Type'));
		$this->assertSame('10', $response->getHeaderLine('Content-Length'));
		$this->assertStringEndsWith('GMT', $response->getHeaderLine('Last-Modified'));
		$this->assertSame('plain text', (string) $response->getBody());
		$this->assertSame('text/markdown', Response::file($path, 'text/markdown')->getHeaderLine('Content-Type'));

		$this->expectException(StreamException::class);
		Response::file($this->temporaryDirectory() . '/missing.txt');
	}

	public function testEmptyStatuses(): void
	{
		$this->assertTrue(Status::NoContent->isEmpty());
		$this->assertTrue(Status::NotModified->isEmpty());
		$this->assertTrue(Status::Continue->isEmpty());
		$this->assertFalse(Status::Ok->isEmpty());
	}

	public function testFileResponsesServeByteRanges(): void
	{
		$path  = $this->writeTemporaryFile('digits.txt', '0123456789');
		$cases = [
			// Range header => [status, Content-Range, body]
			''                => [200, '', '0123456789'],
			'bytes=2-4'       => [206, 'bytes 2-4/10', '234'],
			'bytes=7-'        => [206, 'bytes 7-9/10', '789'],
			'bytes=-3'        => [206, 'bytes 7-9/10', '789'],
			'bytes=-30'       => [206, 'bytes 0-9/10', '0123456789'],
			'bytes=8-100'     => [206, 'bytes 8-9/10', '89'],
			'bytes=10-'       => [416, 'bytes */10', ''],
			'bytes=-0'        => [416, 'bytes */10', ''],
			'bytes=5-2'       => [200, '', '0123456789'],
			'bytes=0-1,4-5'   => [200, '', '0123456789'],
			'items=0-1'       => [200, '', '0123456789']
		];

		foreach ($cases as $range => [$status, $contentRange, $body]) {
			$response = Response::file($path, 'text/plain', range: $range);

			$this->assertSame($status, $response->getStatusCode(), $range);
			$this->assertSame($contentRange, $response->getHeaderLine('Content-Range'), $range);
			$this->assertSame($body, (string) $response->getBody(), $range);
			$this->assertSame('bytes', $response->getHeaderLine('Accept-Ranges'), $range);

			if ($status !== 416) {
				$this->assertSame((string) strlen($body), $response->getHeaderLine('Content-Length'), $range);
			}
		}
	}

	public function testLimitedStreamsReadOnlyTheirWindow(): void
	{
		$stream = new LimitedStream(Stream::fromString('0123456789'), 3, 4);

		$this->assertSame(4, $stream->getSize());
		$this->assertSame('34', $stream->read(2));
		$this->assertSame(2, $stream->tell());
		$this->assertSame('56', $stream->read(10));
		$this->assertTrue($stream->eof());
		$this->assertSame('', $stream->read(1));

		$stream->seek(-1, SEEK_END);
		$this->assertSame('6', $stream->getContents());

		$stream->seek(1);
		$stream->seek(1, SEEK_CUR);
		$this->assertSame('56', $stream->getContents());
		$this->assertSame('3456', (string) $stream);
		$this->assertTrue($stream->isSeekable());
		$this->assertTrue($stream->isReadable());
		$this->assertFalse($stream->isWritable());
		$this->assertIsArray($stream->getMetadata());

		try {
			$stream->seek(5);
			$this->fail('Seeking past the window should fail.');
		} catch (StreamException) {
			$this->addToAssertionCount(1);
		}

		$this->expectException(StreamException::class);

		$stream->write('x');
	}
}
