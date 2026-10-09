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
use Blush\Container\ServiceResolver;
use Blush\Core\Paths;
use Blush\Core\ServiceProvider;
use Blush\Menu\Link\MenuLinkFactory;
use Blush\Menu\Link\MenuLinkRegistrar;
use Blush\Menu\Link\MenuLinkRegistry;
use Blush\Storage\File\FileLayout;
use Blush\Storage\File\FileLayouts;
use Blush\Storage\Record\TableRegistry;

/**
 * Binds menus (D-199): the link kind registry, seeded with the built-ins,
 * its factory, the `menus` table (D-676), kept on files as a folder under
 * `user/data`, each file named for its menu, and `Menus`.
 */
final class MenuServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		MenuLinkFactory::class,
		MenuLoader::class,
		MenuRefs::class,
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

		$this->container->resolving(TableRegistry::class, static function (object $tables): void {
			if ($tables instanceof TableRegistry) {
				$tables->register(MenuLoader::table());
			}
		});

		$this->container->resolving(FileLayouts::class, static function (object $layouts, ServiceResolver $resolver): void {
			if ($layouts instanceof FileLayouts) {
				$layouts->register(MenuLoader::TABLE, FileLayout::folder($resolver->make(Paths::class)->data . '/' . MenuLoader::TABLE, keyInName: true));
			}
		});
	}
}
