<?php

/**
 * High-priority route source fixture.
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Routing;

use Override;
use Blush\Routing\Route;
use Blush\Routing\RoutePriority;
use Blush\Routing\RouteSource;

final readonly class SystemRoutes implements RouteSource
{
	#[Override]
	public function priority(): RoutePriority
	{
		return RoutePriority::System;
	}

	#[Override]
	public function routes(): iterable
	{
		return [
			Route::get('/feed', Page::class)->defaults(['name' => 'system-feed'])->named('feed'),
			Route::get('/pages/{name}', Page::class)
		];
	}
}
