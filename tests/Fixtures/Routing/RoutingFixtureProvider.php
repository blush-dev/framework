<?php

/**
 * Provider fixture tagging a route source and a controller.
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Routing;

use Blush\Core\ServiceProvider;
use Blush\Routing\RouteSource;
use Blush\Routing\Sources\ControllerRoutes;

final class RoutingFixtureProvider extends ServiceProvider
{
	protected const array TAGS = [
		RouteSource::TAG      => [SystemRoutes::class],
		ControllerRoutes::TAG => [Archive::class]
	];
}
