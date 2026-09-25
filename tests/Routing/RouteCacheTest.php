<?php

/**
 * Route cache tests.
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
use Blush\Console\Commands\RoutesList;
use Blush\Console\Console;
use Blush\Console\Testing\CommandTester;
use Blush\Core\Bootstrap;
use Blush\Routing\RouteCache;

#[CoversClass(RouteCache::class)]
#[CoversClass(RoutesList::class)]
final class RouteCacheTest extends TestCase
{
	use BootsRoutedSite;

	private const array PRODUCTION = ['APP_ENV' => 'production'];

	protected function tearDown(): void
	{
		$this->unregisterAutoloader();
	}

	public function testProductionServesTheCompiledTable(): void
	{
		$this->boot(environment: self::PRODUCTION);

		$cache = $this->application?->container()->make(RouteCache::class);
		$cache?->write();

		$this->assertFileExists("{$this->root}/storage/cache/routes.php");

		// The cached table wins over a changed config until it's cleared.
		$this->boot($this->routesConfig(routes: "Route::get('/new', Page::class)"), self::PRODUCTION);

		$this->assertSame(404, $this->request('GET', '/new')->getStatusCode());
		$this->assertSame(200, $this->request('GET', '/about')->getStatusCode());

		$this->application?->container()->make(Bootstrap::class)->clearCompiled();
		$this->boot($this->routesConfig(routes: "Route::get('/new', Page::class)"), self::PRODUCTION);

		$this->assertSame(200, $this->request('GET', '/new')->getStatusCode());
	}

	public function testDevelopmentIgnoresTheCache(): void
	{
		$this->boot();
		$this->application?->container()->make(RouteCache::class)->write();

		$this->boot($this->routesConfig(routes: "Route::get('/new', Page::class)"));

		$this->assertSame(200, $this->request('GET', '/new')->getStatusCode());
	}

	public function testCompileWritesTheRouteTable(): void
	{
		$this->boot(environment: self::PRODUCTION);
		$this->application?->container()->make(Bootstrap::class)->compile();

		$this->assertFileExists("{$this->root}/storage/cache/routes.php");
		$this->assertFileExists("{$this->root}/storage/cache/container.php");
	}

	public function testRoutesListShowsRoutesRedirectsAndShadowing(): void
	{
		$app    = $this->boot();
		$result = new CommandTester($app->container()->make(Console::class))->run('routes:list');

		$this->assertTrue($result->isSuccessful());
		$this->assertMatchesRegularExpression('#\| GET +\| /archives/\{year\} +\| archive\.year +\|#', $result->output);
		$this->assertStringContainsString('| system ', $result->output);
		$this->assertMatchesRegularExpression('#\| /blog/\{slug\} +\| /pages/\{slug\} +\| 302 +\|#', $result->output);
		$this->assertStringContainsString('GET /feed', $result->output . $result->errors);
		$this->assertStringContainsString('is shadowed by', $result->output . $result->errors);
	}
}
