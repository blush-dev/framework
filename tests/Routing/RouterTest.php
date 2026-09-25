<?php

/**
 * Router tests.
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
use Blush\Event\Listener\Listenable;
use Blush\Http\HttpError;
use Blush\Http\MethodNotAllowed;
use Blush\Http\NotFound;
use Blush\Routing\ControllerHandler;
use Blush\Routing\Events\RouteMatched;
use Blush\Routing\Router;
use Blush\Routing\RouteTable;
use Blush\Routing\RoutingServiceProvider;
use Blush\Routing\Sources\ConfigRedirects;
use Blush\Routing\Sources\ConfigRoutes;
use Blush\Routing\Sources\ControllerRoutes;
use Blush\Routing\Sources\FallbackRoutes;

#[CoversClass(Router::class)]
#[CoversClass(ControllerHandler::class)]
#[CoversClass(RouteTable::class)]
#[CoversClass(RoutingServiceProvider::class)]
#[CoversClass(ConfigRoutes::class)]
#[CoversClass(ConfigRedirects::class)]
#[CoversClass(ControllerRoutes::class)]
#[CoversClass(FallbackRoutes::class)]
#[CoversClass(HttpError::class)]
#[CoversClass(NotFound::class)]
#[CoversClass(MethodNotAllowed::class)]
final class RouterTest extends TestCase
{
	use BootsRoutedSite;

	protected function tearDown(): void
	{
		$this->unregisterAutoloader();
	}

	public function testServesStaticRoutes(): void
	{
		$response = $this->request('GET', '/about');

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame('page:about', (string) $response->getBody());
	}

	public function testWelcomePageIsTheFallbackForTheHomePage(): void
	{
		$this->assertStringContainsString('Hello from Fixture Site', (string) $this->request('GET', '/')->getBody());

		$this->boot($this->routesConfig(routes: "Route::get('/', Page::class)"));

		$this->assertSame('page:page', (string) $this->request('GET', '/')->getBody());
	}

	public function testCastsTypedParametersAndPassesTheRequest(): void
	{
		$response = $this->request('GET', '/archives/2024');

		$this->assertSame('year:2024:2024:archive.year', (string) $response->getBody());
		$this->assertSame('tagged', $response->getHeaderLine('X-Route'));
	}

	public function testTypedParametersConstrainTheirSegment(): void
	{
		$this->assertSame(404, $this->request('GET', '/archives/latest')->getStatusCode());
		$this->assertSame('month:2024-05:1', (string) $this->request('GET', '/archives/2024/05')->getBody());
		$this->assertSame(404, $this->request('GET', '/archives/2024/5')->getStatusCode());
	}

	public function testCastsBackedEnums(): void
	{
		$this->assertSame('color:Blue', (string) $this->request('GET', '/archives/color/blue')->getBody());
		$this->assertSame(404, $this->request('GET', '/archives/color/green')->getStatusCode());
	}

	public function testDecodesParameters(): void
	{
		$this->assertSame('page:a b/c.txt', (string) $this->request('GET', '/files/a%20b/c.txt')->getBody());
	}

	public function testUnknownPathsAreNotFound(): void
	{
		$response = $this->request('GET', '/nowhere');

		$this->assertSame(404, $response->getStatusCode());
		$this->assertStringContainsString('404 Not Found', (string) $response->getBody());
	}

	public function testWrongMethodIsNotAllowed(): void
	{
		$response = $this->request('GET', '/archives/search');

		$this->assertSame(405, $response->getStatusCode());
		$this->assertSame('POST, PUT', $response->getHeaderLine('Allow'));

		$response = $this->request('DELETE', '/about');

		$this->assertSame(405, $response->getStatusCode());
		$this->assertSame('GET, HEAD', $response->getHeaderLine('Allow'));
	}

	public function testHeadFallsBackToGet(): void
	{
		$this->assertSame(200, $this->request('HEAD', '/about')->getStatusCode());
	}

	public function testOptionsListsTheAllowedMethods(): void
	{
		$response = $this->request('OPTIONS', '/archives/search');

		$this->assertSame(204, $response->getStatusCode());
		$this->assertSame('POST, PUT, OPTIONS', $response->getHeaderLine('Allow'));
	}

	public function testStaticRoutesWinOverPatterns(): void
	{
		$this->assertSame('page:static-about', (string) $this->request('GET', '/pages/about')->getBody());
		$this->assertSame('page:other', (string) $this->request('GET', '/pages/other')->getBody());
	}

	public function testHigherPriorityRoutesShadowLowerOnes(): void
	{
		$this->assertSame('page:system-feed', (string) $this->request('GET', '/feed')->getBody());

		$shadowed = $this->application?->container()->make(RouteTable::class)->shadowed() ?? [];

		$this->assertCount(1, $shadowed);
		$this->assertSame('/feed', $shadowed[0]['path']);
	}

	public function testRedirectsTheTrailingSlash(): void
	{
		$response = $this->request('GET', '/about/?ref=x');

		$this->assertSame(301, $response->getStatusCode());
		$this->assertSame('/about?ref=x', $response->getHeaderLine('Location'));

		$response = $this->request('POST', '/archives/search/');

		$this->assertSame(308, $response->getStatusCode());
		$this->assertSame('/archives/search', $response->getHeaderLine('Location'));

		$this->assertSame(404, $this->request('GET', '/nowhere/')->getStatusCode());
	}

	public function testTrailingSlashCanBeCanonical(): void
	{
		$this->boot($this->routesConfig(trailingSlash: 'true'));

		$response = $this->request('GET', '/about');

		$this->assertSame(301, $response->getStatusCode());
		$this->assertSame('/about/', $response->getHeaderLine('Location'));
		$this->assertSame('page:about', (string) $this->request('GET', '/about/')->getBody());
		$this->assertSame('page:xml', (string) $this->request('GET', '/feed.xml')->getBody());
		$this->assertSame(200, $this->request('GET', '/')->getStatusCode());
	}

	public function testRedirectsBeforeNotFound(): void
	{
		$response = $this->request('GET', '/old-about');

		$this->assertSame(301, $response->getStatusCode());
		$this->assertSame('/about', $response->getHeaderLine('Location'));

		$response = $this->request('GET', '/blog/hello-world?page=2');

		$this->assertSame(302, $response->getStatusCode());
		$this->assertSame('/pages/hello-world?page=2', $response->getHeaderLine('Location'));
	}

	public function testRedirectsWhenAHandlerFindsNothing(): void
	{
		$this->assertSame('/about', $this->request('GET', '/gone')->getHeaderLine('Location'));
	}

	public function testRedirectsPublicDirectoryUrls(): void
	{
		$response = $this->request('GET', '/public/about');

		$this->assertSame(301, $response->getStatusCode());
		$this->assertSame('/about', $response->getHeaderLine('Location'));
	}

	public function testHandlersMustReturnResponses(): void
	{
		$response = $this->request('GET', '/broken');

		$this->assertSame(500, $response->getStatusCode());
		$this->assertStringContainsString('must return a', (string) $response->getBody());
	}

	public function testDispatchesRouteMatched(): void
	{
		$app     = $this->boot();
		$matched = [];

		$app->container()->make(Listenable::class)->listen(
			RouteMatched::class,
			static function (RouteMatched $event) use (&$matched): void {
				$matched[] = $event->match->route->path();
			}
		);

		$this->request('GET', '/archives/2024');

		$this->assertSame(['/archives/{year}'], $matched);
	}
}
