<?php

/**
 * Region service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Region;

use Override;
use Blush\Core\ServiceProvider;
use Blush\Region\Item\RegionItemFactory;
use Blush\Region\Item\RegionItemRegistrar;
use Blush\Region\Item\RegionItemRegistry;

/**
 * Binds regions (D-201): the item kind registry, seeded with the
 * built-ins, its factory, the region files, and `Regions`.
 */
final class RegionServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		RegionItemFactory::class,
		RegionLoader::class,
		Regions::class
	];

	/**
	 * Binds the item kind registry, seeded with the built-ins.
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singleton(
			RegionItemRegistry::class,
			static function (): RegionItemRegistry {
				$registry = new RegionItemRegistry();
				new RegionItemRegistrar($registry)->register();

				return $registry;
			}
		);
	}
}
