<?php

/**
 * Route config tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Config\InvalidConfig;
use Blush\Http\Status;
use Blush\Routing\Redirect;
use Blush\Routing\Route;
use Blush\Routing\RouteConfig;
use Blush\Tests\Fixtures\Routing\Archive;
use Blush\Tests\Fixtures\Routing\Page;

#[CoversClass(RouteConfig::class)]
#[CoversClass(Route::class)]
#[CoversClass(Redirect::class)]
final class RouteConfigTest extends TestCase
{
	public function testRoundTripsThroughArrays(): void
	{
		$config = new RouteConfig(
			routes: [Route::get('/about', Page::class)->named('about')->defaults(['name' => 'about'])],
			controllers: [Archive::class],
			redirects: [new Redirect('/old', '/new', Status::Found)],
			trailingSlash: true
		);

		$this->assertEquals($config, RouteConfig::fromArray($config->toArray()));
	}

	public function testRejectsBadArrays(): void
	{
		$this->expectException(InvalidConfig::class);

		RouteConfig::fromArray(['routes' => [['path' => '/a', 'controller' => 'App\\Missing']]]);
	}

	public function testRejectsUnknownControllers(): void
	{
		$this->expectException(InvalidConfig::class);

		RouteConfig::fromArray(['controllers' => ['App\\Missing']]);
	}

	public function testCanonicalPaths(): void
	{
		$none = new RouteConfig();
		$with = new RouteConfig(trailingSlash: true);

		$this->assertSame('/', $none->canonicalPath('/'));
		$this->assertSame('/about', $none->canonicalPath('/about/'));
		$this->assertSame('/', $with->canonicalPath('/'));
		$this->assertSame('/about/', $with->canonicalPath('/about'));
		$this->assertSame('/feed.xml', $with->canonicalPath('/feed.xml/'));
	}
}
