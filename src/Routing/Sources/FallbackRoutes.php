<?php

/**
 * Fallback route source.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing\Sources;

use Override;
use Blush\Http\WelcomeHandler;
use Blush\Routing\Route;
use Blush\Routing\RoutePriority;
use Blush\Routing\RouteSource;

/**
 * Routes that anything else may override. For now that's the welcome page
 * at `/`, so a fresh site answers its home page. The page catch-all joins
 * it with content (M4).
 */
final readonly class FallbackRoutes implements RouteSource
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function priority(): RoutePriority
	{
		return RoutePriority::Fallback;
	}

	/**
	 * @inheritDoc
	 * @return list<Route>
	 */
	#[Override]
	public function routes(): iterable
	{
		return [Route::get('/', WelcomeHandler::class)];
	}
}
