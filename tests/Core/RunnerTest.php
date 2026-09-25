<?php

/**
 * Entry point runner tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Console\ConsoleRunner;
use Blush\Core\Runner;
use Blush\Error\ErrorHandler;
use Blush\Extension\LocalAutoloader;
use Blush\Http\HttpRunner;
use Blush\Http\Request;
use Blush\Tests\FixtureSite;

#[CoversClass(Runner::class)]
#[CoversClass(HttpRunner::class)]
#[CoversClass(ConsoleRunner::class)]
final class RunnerTest extends TestCase
{
	use FixtureSite;

	private ?Runner $runner = null;

	/**
	 * The runner registers the configured error handler globally, so
	 * hand error handling back to PHPUnit.
	 */
	protected function tearDown(): void
	{
		$container = $this->runner?->application()->container();

		$container?->make(ErrorHandler::class)->unregister();
		$container?->make(LocalAutoloader::class)->unregister();
	}

	public function testHttpRunnerHandlesRequestsThroughTheKernel(): void
	{
		$this->runner = new HttpRunner($this->fixtureSite(), environment: []);

		$response = $this->runner->handle(Request::create('/'));

		$this->assertSame(200, $response->getStatusCode());
		$this->assertStringContainsString('Hello from Fixture Site', (string) $response->getBody());
		$this->assertTrue($this->runner->application()->isBooted());
		$this->assertSame($this->runner->application(), $this->runner->application());
	}

	public function testConsoleRunnerReturnsTheExitCode(): void
	{
		$root         = $this->fixtureSite();
		$this->runner = new ConsoleRunner($root, environment: []);

		// The fixture runs in development, which never reads the
		// extension cache, so a placeholder is safe to clear.
		mkdir("{$root}/storage/cache", 0775, true);
		file_put_contents("{$root}/storage/cache/extensions.php", '<?php return [];');

		$this->assertSame(0, $this->runner->run(['blush', '--quiet', 'cache:clear', '--extensions']));
		$this->assertFileDoesNotExist("{$root}/storage/cache/extensions.php");
	}

	public function testConsoleRunnerFailsCleanlyWhenConfigIsBroken(): void
	{
		$root   = $this->fixtureSite();
		$errors = fopen('php://memory', 'w+');
		$this->assertNotFalse($errors);

		file_put_contents("{$root}/config/broken.php", '<?php throw new RuntimeException("Broken config.");');

		$runner = new ConsoleRunner($root, environment: [], errors: $errors);

		$this->assertSame(1, $runner->run(['blush', 'list']));

		rewind($errors);
		$this->assertStringContainsString('Broken config.', (string) stream_get_contents($errors));

		// The bare handler stays registered after a failed launch.
		restore_error_handler();
		restore_exception_handler();
	}
}
