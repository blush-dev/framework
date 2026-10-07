<?php

/**
 * Asset service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Asset;

use Override;
use Blush\Core\ServiceProvider;
use Blush\Routing\RouteSource;

/**
 * Binds the asset registry (seeded with core's assets), the URLs, the
 * collector, and the routes for core's and plugins' files (D-569 to
 * D-572). A plugin, theme, or site provider registers its assets in
 * `boot()`:
 *
 *     $this->container->make(AssetRegistry::class)->register(new Asset('acme/gallery', ...));
 */
final class AssetServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		Assets::class,
		AssetUrls::class,
		AssetCollector::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TRANSIENTS = [
		AssetController::class,
		AssetRoutes::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TAGS = [
		RouteSource::TAG => [AssetRoutes::class]
	];

	/**
	 * Binds the registry, seeded with core's assets.
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singleton(
			AssetRegistry::class,
			static function (): AssetRegistry {
				$registry = new AssetRegistry();
				new AssetRegistrar($registry)->register();

				return $registry;
			}
		);
	}
}
