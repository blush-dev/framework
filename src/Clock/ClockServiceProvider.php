<?php

/**
 * Clock service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Clock;

use Override;
use Psr\Clock\ClockInterface;
use Blush\Container\ServiceResolver;
use Blush\Core\AppConfig;
use Blush\Core\ServiceProvider;

/**
 * Binds the PSR-20 clock to the system clock in the site timezone. Tests (or a
 * preview) can replace it with a `FrozenClock` instance.
 */
final class ClockServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singletonIf(
			ClockInterface::class,
			static fn (ServiceResolver $resolver): ClockInterface => new SystemClock(
				$resolver->make(AppConfig::class)->timezone()
			)
		);
	}
}
