<?php

/**
 * Bootstrap tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Core;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Config\ConfigRepository;
use Blush\Console\Commands\Serve;
use Blush\Core\AppConfig;
use Blush\Core\Bootstrap;
use Blush\Core\Environment;
use Blush\Core\Paths;
use Blush\Error\ErrorHandler;
use Blush\Event\Dispatcher;
use Blush\Plugin\Plugins;
use Blush\Extension\LocalAutoloader;
use Blush\Http\Kernel;
use Blush\Http\Middleware\HandleErrors;
use Blush\Log\Logger;
use Blush\Tests\Fixtures\Plugin\ComposerPluginProvider;
use Blush\Tests\Fixtures\Plugin\SiteServiceProvider;
use Blush\Tests\FixtureSite;
use Blush\View\ThemedPageRenderer;

#[CoversClass(Bootstrap::class)]
final class BootstrapTest extends TestCase
{
	use FixtureSite;

	private ?LocalAutoloader $autoloader = null;

	protected function tearDown(): void
	{
		$this->autoloader?->unregister();
	}

	/**
	 * @param array<string, string> $environment
	 */
	private function bootstrap(string $root, array $environment = []): Bootstrap
	{
		return new Bootstrap(Paths::fromRoot($root), $environment);
	}

	public function testBuildsTheApplication(): void
	{
		$root = $this->fixtureSite();
		$app  = $this->bootstrap($root)->createApplication();

		$container        = $app->container();
		$this->autoloader = $container->make(LocalAutoloader::class);

		$config = $container->make(AppConfig::class);

		$this->assertSame('Fixture Site', $config->name);
		$this->assertSame(Environment::Development, $config->environment);
		$this->assertSame($root, $container->make(Paths::class)->root);
		$this->assertSame($config, $container->make(ConfigRepository::class)->get(AppConfig::class));

		$providers = array_map(static fn (object $provider): string => $provider::class, $app->providers());

		$this->assertContains(ComposerPluginProvider::class, $providers);
		$this->assertContains('Fixture\Hello\HelloServiceProvider', $providers);
		$this->assertSame(SiteServiceProvider::class, array_last($providers));
		$this->assertFalse($container->make(Plugins::class)->has('acme/disabled'));
		$this->assertTrue($container->get('composer-plugin.registered'));

		$app->boot();

		$this->assertTrue($container->get('site.booted'));

		$greeter = $container->get('Fixture\Hello\Greeter');
		$this->assertIsObject($greeter);
		$this->assertTrue(method_exists($greeter, 'greet'));
		$this->assertSame('Hello from Fixture Site', $greeter->greet());
	}

	public function testCoreServicesResolve(): void
	{
		$app              = $this->bootstrap($this->fixtureSite())->createApplication();
		$container        = $app->container();
		$this->autoloader = $container->make(LocalAutoloader::class);

		$this->assertInstanceOf(Logger::class, $container->make(LoggerInterface::class));
		$this->assertSame('America/Chicago', $container->make(ClockInterface::class)->now()->getTimezone()->getName());
		$this->assertInstanceOf(ErrorHandler::class, $container->make(ErrorHandler::class));
		$this->assertInstanceOf(Dispatcher::class, $container->make(Dispatcher::class));
	}

	public function testProcessEnvironmentOverridesDotEnv(): void
	{
		$app              = $this->bootstrap($this->fixtureSite(), ['APP_NAME' => 'From Host'])->createApplication();
		$this->autoloader = $app->container()->make(LocalAutoloader::class);

		$this->assertSame('From Host', $app->container()->make(AppConfig::class)->name);
	}

	public function testDefaultsApplyWithoutConfigFiles(): void
	{
		$root = $this->temporaryDirectory() . '/bare';
		mkdir($root);

		$app    = $this->bootstrap($root, ['APP_URL' => 'https://bare.test'])->createApplication();
		$config = $app->container()->make(AppConfig::class);

		$this->assertSame('https://bare.test', $config->url);
		$this->assertSame(Environment::Production, $config->environment);
	}

	public function testCompileWritesCachesThatProductionUses(): void
	{
		$root      = $this->fixtureSite();
		$bootstrap = $this->bootstrap($root, ['APP_ENV' => 'production']);

		$plans = $bootstrap->compile();

		$this->assertGreaterThan(0, $plans);

		// Warm-up plans request-time classes the container knows about
		// (D-066), along with their dependencies.
		$compiled = require "{$root}/storage/cache/container.php";
		$this->assertIsArray($compiled);
		$this->assertArrayHasKey(Kernel::class, $compiled);
		$this->assertArrayHasKey(ThemedPageRenderer::class, $compiled);
		$this->assertArrayHasKey(Serve::class, $compiled);
		$this->assertArrayHasKey(HandleErrors::class, $compiled);

		$this->assertFileExists("{$root}/storage/cache/config.php");
		$this->assertFileExists("{$root}/storage/cache/plugins.php");
		$this->assertFileExists("{$root}/storage/cache/container.php");

		// Once compiled, the config files and plugin folders are no
		// longer read.
		unlink("{$root}/config/app.php");
		unlink("{$root}/extensions/fixture/hello/plugin.json");

		$app              = $bootstrap->createApplication();
		$this->autoloader = $app->container()->make(LocalAutoloader::class);

		$this->assertSame('Fixture Site', $app->container()->make(AppConfig::class)->name);
		$this->assertSame(Environment::Production, $app->container()->make(AppConfig::class)->environment);
		$this->assertTrue($app->container()->make(Plugins::class)->has('fixture/hello'));

		$bootstrap->clearCompiled();

		$this->assertFileDoesNotExist("{$root}/storage/cache/config.php");
		$this->assertFileDoesNotExist("{$root}/storage/cache/container.php");
	}

	public function testCompileKeepsPluginsThatAreOff(): void
	{
		$root      = $this->fixtureSite();
		$bootstrap = $this->bootstrap($root, ['APP_ENV' => 'production']);

		@mkdir("{$root}/user/data", 0777, true);
		file_put_contents("{$root}/user/data/settings.json", '{"plugins": {"enabled": []}}');

		$bootstrap->compile();

		$app = $bootstrap->createApplication();
		$this->assertFalse($app->container()->make(Plugins::class)->has('fixture/hello'));

		// Turned on in the admin later, it's found in the cache (D-385).
		unlink("{$root}/user/data/settings.json");

		$app              = $bootstrap->createApplication();
		$this->autoloader = $app->container()->make(LocalAutoloader::class);

		$this->assertTrue($app->container()->make(Plugins::class)->has('fixture/hello'));
	}
}
