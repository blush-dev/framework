<?php

/**
 * Console test helper.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Console;

use Blush\Console\Console;
use Blush\Console\ConsoleServiceProvider;
use Blush\Console\ProcessRunner;
use Blush\Console\Testing\CommandTester;
use Blush\Container\ServiceContainer;
use Blush\Core\Application;
use Blush\Core\Paths;
use Blush\Core\ServiceProvider;
use Blush\Error\ErrorHandler;
use Blush\Error\ExceptionRenderer;
use Blush\Error\TextRenderer;
use Blush\Tests\Fixtures\Console\ConsoleFixtureProvider;
use Blush\Tests\Fixtures\Console\RecordingProcessRunner;
use Blush\Tests\Fixtures\Log\MemoryLogger;
use Blush\Tests\TemporaryDirectory;

/**
 * Builds an application with the console provider and the fixture commands,
 * with an in-memory logger and a recording process runner.
 */
trait BuildsConsole
{
	use TemporaryDirectory;

	private MemoryLogger $logger;

	private RecordingProcessRunner $processes;

	/**
	 * @param class-string<ServiceProvider> ...$providers More providers to register.
	 */
	private function application(string ...$providers): Application
	{
		$container       = new ServiceContainer();
		$this->logger    = new MemoryLogger();
		$this->processes = new RecordingProcessRunner();

		$app = new Application($container);

		$container->instance(Paths::class, Paths::fromRoot($this->temporaryDirectory()));
		$container->instance(ExceptionRenderer::class, new TextRenderer());
		$container->instance(ErrorHandler::class, new ErrorHandler(new TextRenderer(), $this->logger));
		$container->instance(ProcessRunner::class, $this->processes);

		$app->register(ConsoleServiceProvider::class, ConsoleFixtureProvider::class, ...$providers);
		$app->boot();

		return $app;
	}

	private function tester(?Application $app = null): CommandTester
	{
		$app ??= $this->application();

		return new CommandTester($app->container()->make(Console::class));
	}
}
