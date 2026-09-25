<?php

/**
 * Request tests.
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
use Blush\Http\Request;
use Blush\Http\Stream;
use Blush\Http\UploadedFile;
use Blush\Http\Uri;

#[CoversClass(Request::class)]
final class RequestTest extends TestCase
{
	public function testCreatesARequestForAPath(): void
	{
		$request = Request::create('/blog?page=2&tag[]=art');

		$this->assertSame('GET', $request->getMethod());
		$this->assertSame('/blog?page=2&tag%5B%5D=art', $request->getRequestTarget());
		$this->assertSame(['page' => '2', 'tag' => ['art']], $request->getQueryParams());
		$this->assertSame('GET', $request->getServerParams()['REQUEST_METHOD']);
		$this->assertFalse($request->hasHeader('Host'));
	}

	public function testHostHeaderComesFromTheUri(): void
	{
		$request = Request::create('https://example.com:8443/about', 'HEAD');

		$this->assertSame(['Host' => ['example.com:8443']], $request->getHeaders());
		$this->assertSame('HEAD', $request->getMethod());

		$explicit = new Request('GET', 'https://example.com/', ['host' => 'other.test']);

		$this->assertSame('other.test', $explicit->getHeaderLine('Host'));
	}

	public function testWithUriUpdatesTheHostUnlessPreserved(): void
	{
		$request = Request::create('http://one.test/');
		$uri     = new Uri('http://two.test/path');

		$this->assertSame('two.test', $request->withUri($uri)->getHeaderLine('Host'));
		$this->assertSame('one.test', $request->withUri($uri, preserveHost: true)->getHeaderLine('Host'));
		$this->assertSame('two.test', Request::create('/')->withUri($uri, preserveHost: true)->getHeaderLine('Host'));
		$this->assertSame('/path', $request->withUri($uri)->getRequestTarget());
	}

	public function testRequestTargetAndMethod(): void
	{
		$request = Request::create('/');

		$this->assertSame('*', $request->withRequestTarget('*')->getRequestTarget());
		$this->assertSame('patch', $request->withMethod('patch')->getMethod());
		$this->assertSame('/', new Request('GET', '')->getRequestTarget());

		$this->expectException(InvalidMessage::class);
		(void) $request->withRequestTarget('/with space');
	}

	public function testRejectsInvalidMethods(): void
	{
		$this->expectException(InvalidMessage::class);

		Request::create('/', 'BAD METHOD');
	}

	public function testServerRequestParameters(): void
	{
		$file    = new UploadedFile(Stream::fromString('x'), 1);
		$request = Request::create('/')
			->withCookieParams(['session' => 'abc'])
			->withQueryParams(['q' => 'search'])
			->withParsedBody(['title' => 'Hello'])
			->withUploadedFiles(['files' => [$file]])
			->withAttribute('route', 'home')
			->withAttribute('empty', null);

		$this->assertSame(['session' => 'abc'], $request->getCookieParams());
		$this->assertSame(['q' => 'search'], $request->getQueryParams());
		$this->assertSame(['title' => 'Hello'], $request->getParsedBody());
		$this->assertSame(['files' => [$file]], $request->getUploadedFiles());
		$this->assertSame('home', $request->getAttribute('route'));
		$this->assertNull($request->getAttribute('empty', 'default'));
		$this->assertSame('default', $request->withoutAttribute('route')->getAttribute('route', 'default'));
		$this->assertSame(['route' => 'home', 'empty' => null], $request->getAttributes());
	}

	public function testRejectsInvalidUploadedFiles(): void
	{
		$this->expectException(InvalidMessage::class);

		(void) Request::create('/')->withUploadedFiles(['file' => 'not a file']);
	}

	public function testBodyFromString(): void
	{
		$request = Request::create('/', 'POST', ['Content-Type' => 'text/plain'], 'payload');

		$this->assertSame('payload', (string) $request->getBody());
	}
}
