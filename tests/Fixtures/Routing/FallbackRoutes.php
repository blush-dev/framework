<?php

/**
 * Fixture fallback route source.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Routing;

use Override;
use Blush\Routing\Route;
use Blush\Routing\RoutePriority;
use Blush\Routing\RouteSource;
use Blush\Tests\Fixtures\Http\EchoHandler;

final readonly class FallbackRoutes implements RouteSource
{
	#[Override]
	public function priority(): RoutePriority
	{
		return RoutePriority::Fallback;
	}

	#[Override]
	public function routes(): iterable
	{
		return [Route::get('/', EchoHandler::class)];
	}
}
