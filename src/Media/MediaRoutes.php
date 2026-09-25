<?php

/**
 * Media routes.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use Override;
use Blush\Routing\Route;
use Blush\Routing\RoutePriority;
use Blush\Routing\RouteSource;

/**
 * The media route (`media`): `{url}/{path}`, streamed by
 * `MediaController`. It's a system route, so content can never take over
 * media URLs. Published media is served by the web server before a
 * request reaches it.
 */
final readonly class MediaRoutes implements RouteSource
{
	public function __construct(private MediaConfig $config)
	{}

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
		return [Route::get("{$this->config->url}/{path:.+}", MediaController::class)->named('media')];
	}
}
