<?php

/**
 * Config route source.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing\Sources;

use Override;
use Blush\Routing\Route;
use Blush\Routing\RouteConfig;
use Blush\Routing\RoutePriority;
use Blush\Routing\RouteSource;

/**
 * The routes listed in `config/routes.php`.
 */
final readonly class ConfigRoutes implements RouteSource
{
	public function __construct(private RouteConfig $config)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function priority(): RoutePriority
	{
		return RoutePriority::Config;
	}

	/**
	 * @inheritDoc
	 * @return list<Route>
	 */
	#[Override]
	public function routes(): iterable
	{
		return $this->config->routes;
	}
}
