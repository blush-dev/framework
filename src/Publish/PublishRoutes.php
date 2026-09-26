<?php

/**
 * Publish routes.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Publish;

use Override;
use Blush\Routing\Route;
use Blush\Routing\RoutePriority;
use Blush\Routing\RouteSource;

/**
 * The publish webhook's system route, `publish.webhook` (`POST` at
 * `PublishConfig::$path`), which exists only when a secret is set, so a
 * site that doesn't use it has no endpoint at all.
 */
final readonly class PublishRoutes implements RouteSource
{
	public function __construct(private PublishConfig $config)
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
		return $this->config->hasWebhook()
			? [Route::post($this->config->path, WebhookController::class)->named('publish.webhook')]
			: [];
	}
}
