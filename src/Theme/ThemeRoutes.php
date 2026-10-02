<?php

/**
 * Theme routes.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

use Override;
use Blush\Routing\Route;
use Blush\Routing\RoutePriority;
use Blush\Routing\RouteSource;

/**
 * The theme asset route (`theme.asset`): `/themes/{theme}/{path}`, the
 * theme by its name (`/themes/acme/nova/style.css`, D-378),
 * streamed by `ThemeAssetController`. It's a system route, so content
 * can't take over asset URLs. Themes themselves never add routes (D-020).
 */
final readonly class ThemeRoutes implements RouteSource
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function priority(): RoutePriority
	{
		return RoutePriority::System;
	}

	/**
	 * @inheritDoc
	 * @return list<Route>
	 */
	#[Override]
	public function routes(): iterable
	{
		return [
			Route::get(ThemeChain::ASSET_URL . '/{theme:[a-z0-9][a-z0-9_.-]*/[a-z0-9][a-z0-9_.-]*}/{path:.+}', ThemeAssetController::class)->named('theme.asset')
		];
	}
}
