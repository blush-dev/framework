<?php

/**
 * Menu service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Menu;

use Override;
use Blush\Core\ServiceProvider;
use Blush\Menu\Link\MenuLinkFactory;
use Blush\Menu\Link\MenuLinkRegistrar;
use Blush\Menu\Link\MenuLinkRegistry;

/**
 * Binds menus (D-199): the link kind registry, seeded with the built-ins,
 * its factory, the menu files, and `Menus`.
 */
final class MenuServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		MenuLinkFactory::class,
		MenuLoader::class,
		Menus::class
	];

	/**
	 * Binds the link kind registry, seeded with the built-ins.
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singleton(
			MenuLinkRegistry::class,
			static function (): MenuLinkRegistry {
				$registry = new MenuLinkRegistry();
				new MenuLinkRegistrar($registry)->register();

				return $registry;
			}
		);
	}
}
