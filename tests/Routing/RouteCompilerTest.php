<?php

/**
 * Route compiler tests.
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
use Blush\Http\Status;
use Blush\Http\WelcomeHandler;
use Blush\Routing\CompiledRoute;
use Blush\Routing\InvalidRoute;
use Blush\Routing\Redirect;
use Blush\Routing\Route;
use Blush\Routing\RouteCompiler;
use Blush\Routing\RouteConfig;
use Blush\Routing\RoutePriority;
use Blush\Routing\RouteTable;
use Blush\Routing\Sources\ConfigRedirects;
use Blush\Routing\Sources\ConfigRoutes;
use Blush\Routing\Sources\FallbackRoutes;
use Blush\Tests\Fixtures\Routing\Archive;
use Blush\Tests\Fixtures\Routing\Color;
use Blush\Tests\Fixtures\Routing\Page;
use Blush\Tests\Fixtures\Routing\SystemRoutes;
use Blush\Tests\Fixtures\Routing\Tag;

#[CoversClass(RouteCompiler::class)]
#[CoversClass(RouteTable::class)]
#[CoversClass(CompiledRoute::class)]
#[CoversClass(Route::class)]
#[CoversClass(Redirect::class)]
#[CoversClass(RoutePriority::class)]
final class RouteCompilerTest extends TestCase
{
	/**
	 * Compiles routes from config (plus any extra sources).
	 *
	 * @param list<Route>    $routes
	 * @param list<Redirect> $redirects
	 */
	private function compile(array $routes, array $redirects = [], object ...$sources): RouteTable
	{
		$config = new RouteConfig(routes: $routes, redirects: $redirects);

		/** @var list<\Blush\Routing\RouteSource> $all */
		$all = [new ConfigRoutes($config), ...$sources];

		return new RouteCompiler($all, [new ConfigRedirects($config)])->compile();
	}

	public function testResolvesHandlersAndRecordsParameters(): void
	{
		$table = $this->compile([
			Route::get('/{year}', [Archive::class, 'year'])->named('year'),
			Route::get('/color/{color}', [Archive::class, 'color'])->named('color'),
			Route::get('/', WelcomeHandler::class)->named('home')
		]);

		$year = $table->named('year');

		$this->assertNotNull($year);
		$this->assertSame(['year' => 'int'], $year->casts);
		$this->assertSame(['request'], $year->requestParams);
		$this->assertSame('\d+', $year->pattern->constraint('year'));
		$color = $table->named('color');

		$this->assertNotNull($color);
		$this->assertSame(['color' => Color::class], $color->casts);
		$this->assertSame('(?:red|blue)', $color->pattern->constraint('color'));
		$this->assertSame('handle', $table->named('home')?->action);
		$this->assertSame('__invoke', $this->compile([Route::get('/', Page::class)->named('p')])->named('p')?->action);
	}

	public function testStripsTrailingSlashesFromPaths(): void
	{
		$table = $this->compile([Route::get('/about/', Page::class)]);

		$this->assertNotNull($table->find('GET', '/about'));
	}

	public function testMatchesAcrossChunks(): void
	{
		$routes = [];

		for ($i = 0; $i < RouteCompiler::CHUNK * 2 + 5; $i++) {
			$routes[] = Route::get("/r{$i}/{name}", Page::class);
		}

		$match = $this->compile($routes)->find('GET', '/r66/x');

		$this->assertSame('/r66/{name}', $match?->route->path());
		$this->assertSame(['name' => 'x'], $match->params);
	}

	public function testPriorityOrdersSourcesAndRecordsShadowing(): void
	{
		$table = $this->compile(
			[Route::get('/feed', Page::class), Route::get('/pages/{slug}', Page::class)],
			[],
			new FallbackRoutes(),
			new SystemRoutes()
		);

		$priorities = array_map(static fn (CompiledRoute $route): RoutePriority => $route->priority, $table->routes());

		// Both config routes lose: `/pages/{name}` (system) and
		// `/pages/{slug}` (config) are the same pattern.
		$this->assertSame([RoutePriority::System, RoutePriority::System, RoutePriority::Fallback], $priorities);
		$this->assertSame(['/feed', '/pages/{slug}'], array_column($table->shadowed(), 'path'));
	}

	public function testKeepsTheUnshadowedMethodsOfARoute(): void
	{
		$table = $this->compile([
			Route::post('/form', Page::class),
			Route::match(['GET', 'POST'], '/form', Page::class)
		]);

		$this->assertSame(['POST', 'GET'], $table->methodsFor('/form'));
		$this->assertCount(2, $table->routes());
	}

	public function testRejectsDuplicateNames(): void
	{
		$this->expectException(InvalidRoute::class);
		$this->expectExceptionMessage('"about" is used more than once');

		$this->compile([Route::get('/a', Page::class)->named('about'), Route::get('/b', Page::class)->named('about')]);
	}

	public function testRejectsControllersWithoutAnEntryPoint(): void
	{
		$this->expectException(InvalidRoute::class);
		$this->expectExceptionMessage('name a method');

		$this->compile([Route::get('/', Archive::class)]);
	}

	public function testRejectsMissingMethods(): void
	{
		$this->expectException(InvalidRoute::class);

		$this->compile([Route::get('/', [Archive::class, 'nope'])]);
	}

	public function testRejectsNonMiddleware(): void
	{
		$this->expectException(InvalidRoute::class);

		$this->compile([Route::get('/', Page::class)->middleware(Page::class)]);
	}

	public function testRoutesDescribeThemselves(): void
	{
		$route = Route::get('/a', [Archive::class, 'year'])->middleware(Tag::class)->where('x', '\d+')->defaults(['page' => 1]);

		$this->assertSame(Archive::class . '::year', $route->handlerName());
		$this->assertSame($route->toArray(), Route::fromArray($route->toArray())->toArray());
	}

	public function testGroupsPrefixRoutes(): void
	{
		$routes = Route::group('/admin/', [Route::get('/', Page::class)->named('home'), Route::get('/users', Page::class)], 'admin.', [Tag::class]);

		$this->assertSame(['/admin', '/admin/users'], array_column(array_map(static fn (Route $route): array => $route->toArray(), $routes), 'path'));
		$this->assertSame('admin.home', $routes[0]->name);
		$this->assertSame([Tag::class], $routes[1]->middleware);
	}

	public function testRejectsInvalidRoutes(): void
	{
		$this->expectException(InvalidRoute::class);

		Route::get('about', Page::class);
	}

	public function testRejectsNonRedirectStatuses(): void
	{
		$this->expectException(InvalidRoute::class);

		new Redirect('/a', '/b', Status::Ok);
	}

	public function testFirstRedirectForAPatternWins(): void
	{
		$table = $this->compile([], [
			new Redirect('/old/{slug}', '/new/{slug}'),
			new Redirect('/old/{name}', '/other/{name}'),
			new Redirect('/moved/', 'https://example.com/moved', Status::Found)
		]);

		$this->assertSame('/new/post', $table->redirectFor('/old/post')?->to);
		$this->assertSame('https://example.com/moved', $table->redirectFor('/moved')?->to);
		$this->assertNull($table->redirectFor('/elsewhere'));
		$this->assertCount(2, $table->redirects());
	}

	public function testRoundTripsThroughArrays(): void
	{
		$table = $this->compile([Route::get('/{year}', [Archive::class, 'year'])->named('year')]);
		$copy  = new RouteTable($table->toArray());

		$this->assertEquals($table->named('year'), $copy->named('year'));
		$this->assertSame([Archive::class], $copy->controllers());
	}
}
