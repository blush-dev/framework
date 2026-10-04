<?php

/**
 * Built-in command tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Cache\CacheNamespace;
use Blush\Cache\Caches;
use Blush\Console\Commands\CacheClear;
use Blush\Console\Commands\CacheCompile;
use Blush\Console\Commands\Help;
use Blush\Console\Commands\ListCommands;
use Blush\Console\Commands\Serve;
use Blush\Console\Console;
use Blush\Console\ConsoleServiceProvider;
use Blush\Console\ExitCode;
use Blush\Console\Testing\CommandTester;
use Blush\Core\Application;
use Blush\Core\Bootstrap;
use Blush\Core\Paths;
use Blush\Extension\LocalAutoloader;
use Blush\Tests\FixtureSite;

#[CoversClass(ListCommands::class)]
#[CoversClass(Help::class)]
#[CoversClass(Serve::class)]
#[CoversClass(CacheClear::class)]
#[CoversClass(CacheCompile::class)]
final class BuiltInCommandsTest extends TestCase
{
	use BuildsConsole;
	use FixtureSite;

	private ?LocalAutoloader $autoloader = null;

	protected function tearDown(): void
	{
		$this->autoloader?->unregister();
	}

	/**
	 * Builds the fixture site's application with the console registered.
	 */
	private function site(string $root): Application
	{
		$app = new Bootstrap(Paths::fromRoot($root), ['APP_ENV' => 'production'])->createApplication();
		$app->register(ConsoleServiceProvider::class);
		$app->boot();

		$this->autoloader = $app->container()->make(LocalAutoloader::class);

		return $app;
	}

	public function testServeRunsTheBuiltInServer(): void
	{
		$app    = $this->application();
		$public = $app->container()->make(Paths::class)->public;

		mkdir($public, 0775, true);
		touch("{$public}/index.php");

		$result = $this->tester($app)->run('serve --host=0.0.0.0 -p 8080');

		$this->assertTrue($result->isSuccessful());
		$this->assertStringContainsString('Serving on http://0.0.0.0:8080', $result->output);
		$this->assertSame([[
			'command' => [PHP_BINARY, '-S', '0.0.0.0:8080', '-t', $public, Serve::routerScript()],
			'cwd'     => $public
		]], $this->processes->runs);
		$this->assertFileExists(Serve::routerScript());
	}

	public function testServeStaticServesTheExport(): void
	{
		$app    = $this->application();
		$export = $app->container()->make(Paths::class)->export;

		$this->assertSame(ExitCode::Failure, $this->tester($app)->run('serve --static')->exitCode);

		mkdir($export, 0775, true);
		touch("{$export}/index.html");

		$result = $this->tester($app)->run('serve --static');

		$this->assertTrue($result->isSuccessful());
		$this->assertSame([[
			'command' => [PHP_BINARY, '-S', '127.0.0.1:8000', '-t', $export, Serve::routerScript(true)],
			'cwd'     => $export
		]], $this->processes->runs);
		$this->assertFileExists(Serve::routerScript(true));
	}

	public function testServeNeedsAFrontController(): void
	{
		$result = $this->tester()->run('serve');

		$this->assertSame(ExitCode::Failure, $result->exitCode);
		$this->assertStringContainsString('No front controller found', $result->errors);
		$this->assertSame([], $this->processes->runs);
	}

	public function testServeRejectsBadPorts(): void
	{
		$result = $this->tester()->run('serve --port=70000');

		$this->assertSame(ExitCode::Invalid, $result->exitCode);
		$this->assertStringContainsString('between 1 and 65535', $result->errors);
	}

	public function testCacheCompileAndClear(): void
	{
		$root   = $this->fixtureSite();
		$tester = new CommandTester($this->site($root)->container()->make(Console::class));

		$compiled = $tester->run('cache:compile');

		$this->assertTrue($compiled->isSuccessful());
		$this->assertStringContainsString('Wrote storage/cache/config.php', $compiled->output);
		$this->assertMatchesRegularExpression('/Compiled \d+ container plan\(s\), and cleared the cache store\./', $compiled->output);
		$this->assertFileExists("{$root}/storage/cache/content-version.json");
		$this->assertFileExists("{$root}/storage/cache/container.php");
		$this->assertFileExists("{$root}/storage/cache/routes.php");
		$this->assertFileExists("{$root}/storage/cache/content-types.php");
		$this->assertFileExists("{$root}/storage/cache/icon-packs.php");

		$this->assertTrue($tester->run('cache:clear --icon-packs')->isSuccessful());
		$this->assertFileDoesNotExist("{$root}/storage/cache/icon-packs.php");
		$this->assertFileExists("{$root}/storage/cache/themes.php");

		$partial = $tester->run('cache:clear --config -v');

		$this->assertTrue($partial->isSuccessful());
		$this->assertStringContainsString('Cleared storage/cache/config.php', $partial->output);
		$this->assertStringNotContainsString('cache store', $partial->output);
		$this->assertFileDoesNotExist("{$root}/storage/cache/config.php");
		$this->assertFileExists("{$root}/storage/cache/plugins.php");

		$all = $tester->run('cache:clear');

		$this->assertStringContainsString('Cleared 7 compiled cache(s).', $all->output);
		$this->assertStringContainsString('Cleared the cache store (pages, bodies, fragments); the content version is now', $all->output);
		$this->assertFileDoesNotExist("{$root}/storage/cache/plugins.php");
		$this->assertFileDoesNotExist("{$root}/storage/cache/container.php");
		$this->assertFileDoesNotExist("{$root}/storage/cache/routes.php");
		$this->assertFileDoesNotExist("{$root}/storage/cache/content-types.php");

		$version = (string) file_get_contents("{$root}/storage/cache/content-version.json");
		$store   = $tester->run('cache:clear --store');

		$this->assertStringNotContainsString('compiled', $store->output);
		$this->assertStringContainsString('Cleared the cache store', $store->output);
		$this->assertNotSame($version, file_get_contents("{$root}/storage/cache/content-version.json"));
	}

	public function testCacheClearEmbedsEmptiesTheOEmbedAnswersAndTheStore(): void
	{
		$app    = $this->site($this->fixtureSite());
		$tester = new CommandTester($app->container()->make(Console::class));
		$caches = $app->container()->make(Caches::class);

		$caches->persistent(CacheNamespace::Embeds)->set('answer', ['response' => null]);
		$caches->persistent(CacheNamespace::Pages)->set('page', 'html');

		$tester->run('cache:clear');

		$this->assertTrue($caches->persistent(CacheNamespace::Embeds)->has('answer'));

		$caches->persistent(CacheNamespace::Pages)->set('page', 'html');

		$result = $tester->run('cache:clear --embeds');

		$this->assertTrue($result->isSuccessful());
		$this->assertStringContainsString('Cleared the oEmbed answers.', $result->output);
		$this->assertStringContainsString('Cleared the cache store', $result->output);
		$this->assertStringNotContainsString('compiled', $result->output);
		$this->assertFalse($caches->persistent(CacheNamespace::Embeds)->has('answer'));
		$this->assertFalse($caches->persistent(CacheNamespace::Pages)->has('page'));
	}
}
