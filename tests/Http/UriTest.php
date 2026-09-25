<?php

/**
 * URI tests.
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
use Blush\Http\Uri;

#[CoversClass(Uri::class)]
final class UriTest extends TestCase
{
	public function testParsesComponents(): void
	{
		$uri = new Uri('HTTPS://User:Pass@Example.COM:8443/a/b?x=1&y=2#top');

		$this->assertSame('https', $uri->getScheme());
		$this->assertSame('User:Pass', $uri->getUserInfo());
		$this->assertSame('example.com', $uri->getHost());
		$this->assertSame(8443, $uri->getPort());
		$this->assertSame('User:Pass@example.com:8443', $uri->getAuthority());
		$this->assertSame('/a/b', $uri->getPath());
		$this->assertSame('x=1&y=2', $uri->getQuery());
		$this->assertSame('top', $uri->getFragment());
		$this->assertSame('https://User:Pass@example.com:8443/a/b?x=1&y=2#top', (string) $uri);
	}

	public function testDropsTheDefaultPort(): void
	{
		$uri = new Uri('http://example.com:80/');

		$this->assertNull($uri->getPort());
		$this->assertSame('example.com', $uri->getAuthority());
		$this->assertSame('http://example.com/', (string) $uri);
		$this->assertSame(443, $uri->withScheme('http')->withPort(443)->getPort());
		$this->assertNull($uri->withScheme('https')->withPort(443)->getPort());
	}

	public function testEncodesInvalidCharactersWithoutDoubleEncoding(): void
	{
		$uri = new Uri('/a b/c%20d?q=a b&p=100%#f g');

		$this->assertSame('/a%20b/c%20d', $uri->getPath());
		$this->assertSame('q=a%20b&p=100%25', $uri->getQuery());
		$this->assertSame('f%20g', $uri->getFragment());
	}

	public function testRelativeReferences(): void
	{
		$this->assertSame('/path?x=1', (string) new Uri('/path?x=1'));
		$this->assertSame('', (string) new Uri(''));
		$this->assertSame('', new Uri('/path')->getHost());
	}

	public function testWithersAreImmutable(): void
	{
		$uri  = new Uri('http://example.com/a');
		$next = $uri->withScheme('HTTPS')
			->withUserInfo('me', 'p@ss')
			->withHost('Blush.TEST')
			->withPort(8080)
			->withPath('/b c')
			->withQuery('?q=é')
			->withFragment('#frag');

		$this->assertSame('http://example.com/a', (string) $uri);
		$this->assertSame('https://me:p%40ss@blush.test:8080/b%20c?q=%C3%A9#frag', (string) $next);
		$this->assertSame('', $next->withUserInfo('')->getUserInfo());
		$this->assertNull($next->withPort(null)->getPort());
	}

	public function testPathRulesWhenBuildingStrings(): void
	{
		$this->assertSame('http://example.com/rootless', (string) new Uri('http://example.com')->withPath('rootless'));
		$this->assertSame('/double', (string) new Uri('')->withPath('//double'));
	}

	public function testIpv6Hosts(): void
	{
		$uri = new Uri('http://[::1]:8080/');

		$this->assertSame('[::1]', $uri->getHost());
		$this->assertSame(8080, $uri->getPort());
	}

	/**
	 * @return iterable<string, array{callable(Uri): Uri}>
	 */
	public static function invalidChanges(): iterable
	{
		yield 'scheme' => [static fn (Uri $uri): Uri => $uri->withScheme('1http')];
		yield 'port'   => [static fn (Uri $uri): Uri => $uri->withPort(70000)];
		yield 'host'   => [static fn (Uri $uri): Uri => $uri->withHost('bad host')];
	}

	/**
	 * @param callable(Uri): Uri $change
	 */
	#[DataProvider('invalidChanges')]
	public function testRejectsInvalidComponents(callable $change): void
	{
		$this->expectException(InvalidMessage::class);

		$change(new Uri('http://example.com'));
	}

	public function testRejectsUnparseableUris(): void
	{
		$this->expectException(InvalidMessage::class);

		new Uri('http://exa mple.com:port/');
	}
}
