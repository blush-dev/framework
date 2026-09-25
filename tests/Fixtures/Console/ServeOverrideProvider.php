<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Console;

use Blush\Console\CommandRegistry;
use Blush\Core\ServiceProvider;

final class ServeOverrideProvider extends ServiceProvider
{
	protected const array TAGS = [
		CommandRegistry::TAG => [CustomServe::class]
	];
}
