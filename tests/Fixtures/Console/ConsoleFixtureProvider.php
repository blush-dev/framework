<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Console;

use Blush\Console\CommandRegistry;
use Blush\Core\ServiceProvider;

final class ConsoleFixtureProvider extends ServiceProvider
{
	protected const array TAGS = [
		CommandRegistry::TAG => [Greet::class, Copy::class, Explode::class, Interview::class]
	];
}
