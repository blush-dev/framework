<?php

/**
 * Entry point runner base.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Core;

use Throwable;
use Blush\Env\Env;
use Blush\Env\EnvException;
use Blush\Error\ErrorHandler;

/**
 * The shared start of every entry point (`public/index.php`, `bin/blush`),
 * which keeps them down to a couple of lines. Errors are handled in two
 * stages (D-064):
 *
 * 1. Before anything loads, a bare error handler is registered: generic
 *    output with no logger, detailed only when `APP_DEBUG` is set in the
 *    process environment. A broken `.env` or config file still fails
 *    cleanly.
 * 2. Once the application is built and booted, the bare handler is
 *    swapped for the configured `ErrorHandler`.
 */
abstract class Runner
{
	/**
	 * The launched application.
	 */
	private ?Application $application = null;

	/**
	 * The bare error handler, while the application is launching.
	 */
	private ?ErrorHandler $earlyHandler = null;

	/**
	 * The process environment.
	 *
	 * @var array<string, string>
	 */
	protected readonly array $environment;

	/**
	 * @param string                 $root        The project root.
	 * @param array<string, string>  $paths       Path overrides (see `Paths`), such as `public`.
	 * @param ?array<string, string> $environment The process environment; defaults to `getenv()`.
	 */
	public function __construct(
		protected readonly string $root,
		protected readonly array $paths = [],
		?array $environment = null
	) {
		$this->environment = $environment ?? getenv();
	}

	/**
	 * Builds the bare error handler registered before the application
	 * loads.
	 */
	abstract protected function earlyErrorHandler(bool $debug): ErrorHandler;

	/**
	 * Returns the application, building and booting it on first use.
	 */
	public function application(): Application
	{
		return $this->application ??= $this->launch();
	}

	/**
	 * Builds and boots the application behind the bare error handler, then
	 * hands error handling over to the configured one.
	 */
	private function launch(): Application
	{
		$this->earlyHandler = $this->earlyErrorHandler($this->isDebug());
		$this->earlyHandler->register();

		$application = new Bootstrap($this->sitePaths(), $this->environment)
			->createApplication();

		$application->boot();

		$this->earlyHandler->unregister();
		$this->earlyHandler = null;

		$application->container()->make(ErrorHandler::class)->register();

		return $application;
	}

	/**
	 * Returns the site's paths.
	 */
	protected function sitePaths(): Paths
	{
		return Paths::fromRoot($this->root, $this->paths);
	}

	/**
	 * Reports an exception that stopped the application from launching,
	 * through the bare error handler. For entry points that must finish
	 * normally, such as the console returning an exit code.
	 */
	protected function failedToLaunch(Throwable $exception): void
	{
		($this->earlyHandler ?? $this->earlyErrorHandler($this->isDebug()))->handleException($exception);
	}

	/**
	 * Whether `APP_DEBUG` is on in the process environment. `.env` isn't
	 * read yet at this point, and an unreadable value means off.
	 */
	private function isDebug(): bool
	{
		try {
			return new Env($this->environment)->bool('APP_DEBUG', false);
		} catch (EnvException) {
			return false;
		}
	}
}
