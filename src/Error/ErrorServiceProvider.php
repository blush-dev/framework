<?php

/**
 * Error service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Error;

use Override;
use Psr\Log\LoggerInterface;
use Blush\Container\ServiceResolver;
use Blush\Core\AppConfig;
use Blush\Core\ServiceProvider;

/**
 * Binds the error handler and a renderer suited to the SAPI: plain text on
 * the CLI (written to stderr), HTML otherwise. Detail follows
 * `AppConfig::$debug`. Registering the handler is left to the entry point
 * (the front controller or `bin/blush`).
 */
final class ErrorServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singletonIf(
			ExceptionRenderer::class,
			static function (ServiceResolver $resolver): ExceptionRenderer {
				$debug = $resolver->make(AppConfig::class)->debug;

				return PHP_SAPI === 'cli' ? new TextRenderer($debug) : new HtmlRenderer($debug);
			}
		);

		$this->container->singleton(
			ErrorHandler::class,
			static fn (ServiceResolver $resolver): ErrorHandler => new ErrorHandler(
				renderer: $resolver->make(ExceptionRenderer::class),
				logger: $resolver->make(LoggerInterface::class),
				output: PHP_SAPI === 'cli'
					? static function (string $text): void {
						fwrite(STDERR, $text);
					}
					: null
			)
		);
	}
}
