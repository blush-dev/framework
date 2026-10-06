<?php

/**
 * Log service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Log;

use Override;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Blush\Container\ServiceResolver;
use Blush\Core\Paths;
use Blush\Core\ServiceProvider;

/**
 * Binds the PSR-3 logger, configured by `LogConfig`. The logger is only built
 * when something asks for it.
 */
final class LogServiceProvider extends ServiceProvider
{
	/**
	 * Services that type-hint the PSR-3 interface get the Blush logger.
	 */
	protected const array ALIASES = [
		LoggerInterface::class => Logger::class
	];

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singletonIf(LogConfig::class, static fn (): LogConfig => new LogConfig());

		$this->container->singleton(Logger::class, static function (ServiceResolver $resolver): Logger {
			$config = $resolver->make(LogConfig::class);

			return new Logger(
				writer: match ($config->driver) {
					LogDriver::File   => new FileWriter((string) $config->path($resolver->make(Paths::class)->logs)),
					LogDriver::Stderr => new StreamWriter(),
					LogDriver::Null   => new NullWriter()
				},
				clock: $resolver->make(ClockInterface::class),
				threshold: $config->level,
				channel: $config->channel
			);
		});
	}
}
