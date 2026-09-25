<?php

/**
 * Routing service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing;

use Override;
use Psr\Http\Server\RequestHandlerInterface;
use Blush\Container\Container;
use Blush\Core\ServiceProvider;
use Blush\Http\Kernel;
use Blush\Routing\Sources\ConfigRedirects;
use Blush\Routing\Sources\ConfigRoutes;
use Blush\Routing\Sources\ControllerRoutes;

/**
 * Wires the router in as the kernel's handler, along with the route table,
 * its cache, and the URL generator. The table is loaded on first use; the
 * route sources are only built when it has to be compiled.
 *
 * Extensions add routes by tagging a `RouteSource` with `RouteSource::TAG`
 * or a controller with `ControllerRoutes::TAG`, and redirects by tagging a
 * `RedirectSource` with `RedirectSource::TAG`.
 */
final class RoutingServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		Router::class,
		RouteCache::class,
		UrlGenerator::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TRANSIENTS = [
		RouteCompiler::class,
		ConfigRoutes::class,
		ConfigRedirects::class,
		ControllerRoutes::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TAGS = [
		RouteSource::TAG    => [
			ConfigRoutes::class,
			ControllerRoutes::class
		],
		RedirectSource::TAG => [
			ConfigRedirects::class
		]
	];

	/**
	 * Binds the route table and makes the router the kernel's handler.
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singleton(
			RouteTable::class,
			static fn (Container $container): RouteTable => $container->make(RouteCache::class)->load()
		);

		$this->container->whenNeedsType(Kernel::class, RequestHandlerInterface::class, Router::class);
	}
}
