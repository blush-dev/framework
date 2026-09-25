<?php

/**
 * Message header tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Blush\Http\InvalidMessage;
use Blush\Http\Message;
use Blush\Http\Response;
use Blush\Http\Stream;

#[CoversClass(Message::class)]
final class MessageTest extends TestCase
{
	public function testHeadersAreCaseInsensitiveAndKeepTheirCase(): void
	{
		$response = new Response(headers: ['X-Custom' => 'a', 'x-custom' => ['b']]);

		$this->assertSame(['X-Custom' => ['a', 'b']], $response->getHeaders());
		$this->assertTrue($response->hasHeader('X-CUSTOM'));
		$this->assertSame('a, b', $response->getHeaderLine('x-custom'));
		$this->assertSame([], $response->getHeader('Missing'));
	}

	public function testHeaderWithersAreImmutable(): void
	{
		$response = new Response(headers: ['Cache-Control' => 'no-cache']);

		$replaced = $response->withHeader('cache-control', ['public', 'max-age=60']);
		$added    = $replaced->withAddedHeader('CACHE-CONTROL', 'immutable')->withAddedHeader('Vary', 'Accept');
		$removed  = $added->withoutHeader('cache-control');

		$this->assertSame(['Cache-Control' => ['no-cache']], $response->getHeaders());
		$this->assertSame(['cache-control' => ['public', 'max-age=60']], $replaced->getHeaders());
		$this->assertSame('public, max-age=60, immutable', $added->getHeaderLine('Cache-Control'));
		$this->assertSame(['Vary' => ['Accept']], $removed->getHeaders());
		$this->assertSame($removed, $removed->withoutHeader('Missing'));
	}

	public function testValuesAreTrimmed(): void
	{
		$response = new Response()->withHeader('X-Pad', " \tvalue\t ");

		$this->assertSame(['value'], $response->getHeader('x-pad'));
	}

	/**
	 * @return iterable<string, array{string, string|list<string>}>
	 */
	public static function invalidHeaders(): iterable
	{
		yield 'name with space' => ['Bad Name', 'value'];
		yield 'name with colon' => ['Bad:Name', 'value'];
		yield 'value with CRLF' => ['X-Test', "value\r\nInjected: yes"];
		yield 'empty list'      => ['X-Test', []];
	}

	/**
	 * @param string|list<string> $value
	 */
	#[DataProvider('invalidHeaders')]
	public function testRejectsInvalidHeaders(string $name, string|array $value): void
	{
		$this->expectException(InvalidMessage::class);

		(void) new Response()->withHeader($name, $value);
	}

	public function testProtocolAndBody(): void
	{
		$response = new Response();
		$body     = Stream::fromString('body');

		$this->assertSame('1.1', $response->getProtocolVersion());
		$this->assertSame('2', $response->withProtocolVersion('2')->getProtocolVersion());
		$this->assertSame('', (string) $response->getBody());
		$this->assertSame($body, $response->withBody($body)->getBody());

		$this->expectException(InvalidMessage::class);
		(void) $response->withProtocolVersion('9.9');
	}
}
