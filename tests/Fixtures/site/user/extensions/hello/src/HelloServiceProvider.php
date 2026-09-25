<?php

/**
 * Fixture local extension provider.
 */

declare(strict_types=1);

namespace Fixture\Hello;

use Blush\Core\ServiceProvider;

final class HelloServiceProvider extends ServiceProvider
{
	protected const array SINGLETONS = [
		Greeter::class
	];
}
