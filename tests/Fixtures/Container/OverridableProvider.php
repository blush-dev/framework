<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container;

use Blush\Core\ServiceProvider;

final class OverridableProvider extends ServiceProvider
{
	protected const array SINGLETONS_IF = [
		Cache::class => NullCache::class
	];

	protected const array TRANSIENTS_IF = [
		TransientService::class
	];
}
