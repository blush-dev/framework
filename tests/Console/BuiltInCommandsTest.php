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

		$partial = $tester->run('cache:clear --config -v');

		$this->assertTrue($partial->isSuccessful());
		$this->assertStringContainsString('Cleared storage/cache/config.php', $partial->output);
		$this->assertStringNotContainsString('cache store', $partial->output);
		$this->assertFileDoesNotExist("{$root}/storage/cache/config.php");
		$this->assertFileExists("{$root}/storage/cache/extensions.php");

		$all = $tester->run('cache:clear');

		$this->assertStringContainsString('Cleared 6 compiled cache(s).', $all->output);
		$this->assertStringContainsString('Cleared the cache store (pages, bodies, tokens, fragments); the content version is now', $all->output);
		$this->assertFileDoesNotExist("{$root}/storage/cache/extensions.php");
		$this->assertFileDoesNotExist("{$root}/storage/cache/container.php");
		$this->assertFileDoesNotExist("{$root}/storage/cache/routes.php");
		$this->assertFileDoesNotExist("{$root}/storage/cache/content-types.php");

		$version = (string) file_get_contents("{$root}/storage/cache/content-version.json");
		$store   = $tester->run('cache:clear --store');

		$this->assertStringNotContainsString('compiled', $store->output);
		$this->assertStringContainsString('Cleared the cache store', $store->output);
		$this->assertNotSame($version, file_get_contents("{$root}/storage/cache/content-version.json"));
	}
}
