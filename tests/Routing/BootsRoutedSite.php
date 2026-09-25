<?php

/**
 * Routed site test helper.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Routing;

use Psr\Http\Message\ResponseInterface;
use Blush\Core\Application;
use Blush\Core\Bootstrap;
use Blush\Core\Paths;
use Blush\Extension\LocalAutoloader;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Tests\Fixtures\Routing\RoutingFixtureProvider;
use Blush\Tests\FixtureSite;

/**
 * Boots the fixture site with a `config/routes.php` and the routing fixture
 * provider (a system route source and a tagged controller).
 */
trait BootsRoutedSite
{
	use FixtureSite;

	private ?Application $application = null;

	private string $root = '';

	/**
	 * The default routes config: routes, redirects, and `$trailingSlash`.
	 */
	private function routesConfig(string $trailingSlash = 'false', string $routes = ''): string
	{
		return <<<PHP
			<?php

			declare(strict_types=1);

			use Blush\Http\Status;
			use Blush\Routing\Redirect;
			use Blush\Routing\Route;
			use Blush\Routing\RouteConfig;
			use Blush\Tests\Fixtures\Routing\Gone;
			use Blush\Tests\Fixtures\Routing\NotAResponse;
			use Blush\Tests\Fixtures\Routing\Page;

			return new RouteConfig(
				routes: [
					Route::get('/about', Page::class)->defaults(['name' => 'about'])->named('about'),
					Route::get('/feed', Page::class)->defaults(['name' => 'config-feed']),
					Route::get('/pages/about', Page::class)->defaults(['name' => 'static-about']),
					Route::get('/feed.xml', Page::class)->defaults(['name' => 'xml']),
					Route::get('/files/{name:.+}', Page::class)->named('file'),
					Route::get('/gone', Gone::class),
					Route::get('/broken', NotAResponse::class),
					{$routes}
				],
				redirects: [
					new Redirect('/old-about', '/about'),
					new Redirect('/blog/{slug}', '/pages/{slug}', Status::Found),
					new Redirect('/gone', '/about')
				],
				trailingSlash: {$trailingSlash}
			);
			PHP;
	}

	/**
	 * Boots the site. `$routes` is the `config/routes.php` source, and
	 * `$environment` overrides the process environment. Booting again
	 * reuses the same site copy.
	 *
	 * @param array<string, string> $environment
	 */
	private function boot(?string $routes = null, array $environment = []): Application
	{
		$this->unregisterAutoloader();

		$this->root = $this->root === '' ? $this->fixtureSite() : $this->root;

		file_put_contents("{$this->root}/config/routes.php", $routes ?? $this->routesConfig());

		$this->application = new Bootstrap(Paths::fromRoot($this->root), $environment)->createApplication();
		$this->application->register(RoutingFixtureProvider::class);
		$this->application->boot();

		return $this->application;
	}

	/**
	 * Sends a request through the kernel.
	 */
	private function request(string $method, string $uri): ResponseInterface
	{
		$app = $this->application ?? $this->boot();

		return $app->container()->make(Kernel::class)->handle(Request::create($uri, $method));
	}

	private function unregisterAutoloader(): void
	{
		$this->application?->container()->make(LocalAutoloader::class)->unregister();
	}
}
