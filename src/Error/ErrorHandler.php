<?php

/**
 * Error handler.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Error;

use Closure;
use ErrorException;
use Throwable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * The global safety net for errors nothing else caught:
 *
 * - PHP warnings and notices become `ErrorException`s, so they can't pass
 *   silently. Deprecations are logged instead of thrown. Errors silenced
 *   with `@` or excluded by `error_reporting` are left alone.
 * - Uncaught exceptions are logged and rendered.
 * - Fatal errors are caught at shutdown, logged with PHP 8.5's fatal
 *   backtrace, and rendered.
 *
 * The HTTP kernel (M2) handles exceptions per request; this handler covers
 * everything outside it. `register()` is the only thing that touches PHP's
 * global handlers, and `unregister()` restores them.
 */
final class ErrorHandler
{
	/**
	 * Error types that end the script and only reach a shutdown function.
	 */
	private const int FATAL = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;

	/**
	 * Whether the handler is currently registered.
	 */
	private bool $registered = false;

	/**
	 * Memory released at shutdown so an out-of-memory fatal can still be
	 * logged and rendered.
	 */
	private ?string $reserved = null;

	/**
	 * Writes rendered output.
	 *
	 * @var Closure(string): void
	 */
	private readonly Closure $output;

	/**
	 * @param ?Closure(string): void $output Writes rendered output; defaults to `echo`.
	 */
	public function __construct(
		private readonly ExceptionRenderer $renderer,
		private readonly LoggerInterface $logger = new NullLogger(),
		?Closure $output = null
	) {
		$this->output = $output ?? static function (string $text): void {
			echo $text;
		};
	}

	/**
	 * Installs the error, exception, and shutdown handlers.
	 */
	public function register(): void
	{
		if ($this->registered) {
			return;
		}

		set_error_handler($this->handleError(...));
		set_exception_handler($this->handleException(...));
		register_shutdown_function($this->handleShutdown(...));

		$this->reserved   = str_repeat(' ', 32 * 1024);
		$this->registered = true;
	}

	/**
	 * Restores the previous error and exception handlers. (A shutdown
	 * function can't be removed; it does nothing once unregistered.)
	 */
	public function unregister(): void
	{
		if (! $this->registered) {
			return;
		}

		restore_error_handler();
		restore_exception_handler();

		$this->reserved   = null;
		$this->registered = false;
	}

	/**
	 * Converts a PHP error to an exception, or logs a deprecation.
	 *
	 * @throws ErrorException
	 */
	public function handleError(int $level, string $message, string $file = '', int $line = 0): bool
	{
		if ((error_reporting() & $level) === 0) {
			return false;
		}

		if ($level === E_DEPRECATED || $level === E_USER_DEPRECATED) {
			$this->logger->notice('Deprecated: {message} in {file}:{line}', [
				'message' => $message,
				'file'    => $file,
				'line'    => $line
			]);

			return true;
		}

		throw new ErrorException($message, 0, $level, $file, $line);
	}

	/**
	 * Logs and renders an uncaught exception.
	 */
	public function handleException(Throwable $exception): void
	{
		$this->report($exception);
		$this->emit($exception);
	}

	/**
	 * Logs and renders a fatal error at shutdown.
	 */
	public function handleShutdown(): void
	{
		if (! $this->registered) {
			return;
		}

		// Release the reserve so an out-of-memory fatal has room to be
		// logged and rendered.
		if ($this->reserved !== null) {
			$this->reserved = null;
		}

		$error = error_get_last();

		if ($error === null || ($error['type'] & self::FATAL) === 0) {
			return;
		}

		$this->handleException(FatalError::fromLastError($error));
	}

	/**
	 * Logs an exception. Logging must never throw from here, so a failing
	 * logger is ignored.
	 */
	public function report(Throwable $exception): void
	{
		$context = ['exception' => $exception];

		if ($exception instanceof FatalError && $exception->fatalTrace !== []) {
			$context['trace'] = $exception->fatalTraceAsString();
		}

		try {
			$this->logger->critical('Uncaught {class}: {message}', [
				'class'   => $exception::class,
				'message' => $exception->getMessage(),
				...$context
			]);
		} catch (Throwable) {
			// Nothing sensible left to do.
		}
	}

	/**
	 * Renders an exception as a 500 response (or CLI output).
	 */
	private function emit(Throwable $exception): void
	{
		try {
			$output = $this->renderer->render($exception);
		} catch (Throwable) {
			$output = 'An error occurred.';
		}

		if (PHP_SAPI !== 'cli' && ! headers_sent()) {
			http_response_code(500);
			header('Content-Type: ' . $this->renderer->contentType());
		}

		($this->output)($output);
	}
}
