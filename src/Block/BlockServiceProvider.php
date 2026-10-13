<?php

/**
 * Block service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Block;

use Override;
use Blush\Core\ServiceProvider;
use Blush\Theme\Themes;

/**
 * Binds blocks (D-026, D-532): the registry, seeded with the
 * built-ins, and the Markdown block renderer, a default an extension
 * can replace by binding its own.
 *
 * A plugin's provider registers a block in `boot()` (a theme can't):
 *
 *     $this->container->make(BlockRegistry::class)->register('acme/pricing', Pricing::class);
 */
final class BlockServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS_IF = [
		BlockRenderer::class => RegisteredBlocks::class
	];

	/**
	 * Binds the block registry, seeded with the built-ins.
	 */
	#[Override]
	public function register(): void
	{
		$container = $this->container;

		$this->container->singleton(
			BlockRegistry::class,
			static function () use ($container): BlockRegistry {
				// Themes can't register blocks (D-532); the installed
				// themes are looked up only when a provider registers one.
				$registry = new BlockRegistry(static fn (string $namespace): bool => $container->make(Themes::class)->byNamespace($namespace) !== null);
				new BlockRegistrar($registry)->register();

				return $registry;
			}
		);
	}
}
