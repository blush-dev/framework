<?php

/**
 * HTTP config tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Http;

use stdClass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Config\InvalidConfig;
use Blush\Http\HttpConfig;
use Blush\Tests\Fixtures\Http\AddHeader;

#[CoversClass(HttpConfig::class)]
final class HttpConfigTest extends TestCase
{
	public function testRoundTripsThroughArrays(): void
	{
		$config = HttpConfig::fromArray(['middleware' => [AddHeader::class]]);

		$this->assertSame([AddHeader::class], $config->middleware);
		$this->assertEquals($config, HttpConfig::fromArray($config->toArray()));
		$this->assertSame([], new HttpConfig()->middleware);
	}

	public function testRejectsNonMiddleware(): void
	{
		$this->expectException(InvalidConfig::class);

		HttpConfig::fromArray(['middleware' => [stdClass::class]]);
	}

	public function testRejectsUnknownKeys(): void
	{
		$this->expectException(InvalidConfig::class);

		HttpConfig::fromArray(['middlewares' => []]);
	}
}
