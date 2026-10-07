<?php

/**
 * Asset routes.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Asset;

use Override;
use Blush\Routing\Route;
use Blush\Routing\RoutePriority;
use Blush\Routing\RouteSource;

/**
 * The routes for core's and plugins' asset files (D-569), streamed by
 * `AssetController`, as `theme.asset` streams themes' files:
 *
 * - `core.asset`: `/blush/{path}`, core's built site files.
 * - `plugin.asset`: `/extensions/{vendor}/{name}/{path}`, a file in a
 *   plugin that runs.
 *
 * They're system routes, so content can't take over asset URLs.
 */
final readonly class AssetRoutes implements RouteSource
{
	/**
	 * The URL path core's site files are served under.
	 */
	public const string CORE_URL = '/blush';

	/**
	 * The URL path plugins' files are served under.
	 */
	public const string PLUGIN_URL = '/extensions';

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
			Route::get(self::CORE_URL . '/{path:.+}', [AssetController::class, 'core'])->named('core.asset'),
			Route::get(self::PLUGIN_URL . '/{plugin:[a-z0-9][a-z0-9_.-]*/[a-z0-9][a-z0-9_.-]*}/{path:.+}', [AssetController::class, 'plugin'])->named('plugin.asset')
		];
	}
}
