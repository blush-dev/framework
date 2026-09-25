<?php

/**
 * Kernel tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Core\Application;
use Blush\Core\Bootstrap;
use Blush\Core\Paths;
use Blush\Event\Listener\Listenable;
use Blush\Extension\LocalAutoloader;
use Blush\Http\Events\RequestReceived;
use Blush\Http\Events\ResponseReady;
use Blush\Http\HttpFactory;
use Blush\Http\HttpServiceProvider;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Http\WelcomeHandler;
use Blush\Tests\Fixtures\Http\AddHeader;
use Blush\Tests\Fixtures\Http\Failing;
use Blush\Tests\Fixtures\Http\ResponseRecorder;
use Blush\Tests\FixtureSite;

#[CoversClass(Kernel::class)]
#[CoversClass(HttpServiceProvider::class)]
#[CoversClass(WelcomeHandler::class)]
final class KernelTest extends TestCase
{
	use FixtureSite;

	private ?Application $application = null;

	protected function tearDown(): void
	{
		$this->application?->container()->make(LocalAutoloader::class)->unregister();
	}

	/**
	 * Boots the fixture site, optionally with a `config/http.php`.
	 */
	private function boot(?string $httpConfig = null): Application
	{
		$root = $this->fixtureSite();

		if ($httpConfig !== null) {
			file_put_contents("{$root}/config/http.php", $httpConfig);
		}

		$this->application = new Bootstrap(Paths::fromRoot($root))->createApplication();
		$this->application->boot();

		return $this->application;
	}

	public function testServesHello(): void
	{
		$kernel   = $this->boot()->container()->make(Kernel::class);
		$response = $kernel->handle(Request::create('/'));

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
		$this->assertStringContainsString('<h1>Hello from Fixture Site</h1>', (string) $response->getBody());
	}

	public function testRunsConfiguredMiddleware(): void
	{
		$kernel = $this->boot(sprintf(
			'<?php return new Blush\Http\HttpConfig(middleware: [%s::class]);',
			AddHeader::class
		))->container()->make(Kernel::class);

		$this->assertSame('outer', $kernel->handle(Request::create('/'))->getHeaderLine('X-Trace'));
	}

	public function testExceptionsBecomeErrorResponses(): void
	{
		$kernel = $this->boot(sprintf(
			'<?php return new Blush\Http\HttpConfig(middleware: [%s::class]);',
			Failing::class
		))->container()->make(Kernel::class);

		$response = $kernel->handle(Request::create('/'));

		$this->assertSame(500, $response->getStatusCode());

		// The fixture site runs with APP_DEBUG on, so the page has details.
		$this->assertStringContainsString('Middleware exploded', (string) $response->getBody());
	}

	public function testDispatchesRequestAndResponseEvents(): void
	{
		$container = $this->boot()->container();
		$recorder  = new ResponseRecorder();
		$listeners = $container->make(Listenable::class);

		$listeners->listen(RequestReceived::class, $recorder->received(...));
		$listeners->listen(ResponseReady::class, $recorder->ready(...));

		$container->make(Kernel::class)->handle(Request::create('/about'));

		$this->assertSame(['received /about', 'ready 200'], $recorder->events);
	}

	public function testBindsThePsr17Factories(): void
	{
		$container = $this->boot()->container();

		$this->assertInstanceOf(HttpFactory::class, $container->get(ResponseFactoryInterface::class));
		$this->assertSame($container->get(ResponseFactoryInterface::class), $container->get(UriFactoryInterface::class));
	}
}
