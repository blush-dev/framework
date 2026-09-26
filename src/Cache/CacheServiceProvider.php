<?php

/**
 * Cache service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Cache;

use Override;
use Blush\Content\Entry\BodyCache;
use Blush\Content\Events\ContentIndexed;
use Blush\Core\ServiceProvider;
use Blush\Event\Listener\ListenerRegistry;
use Blush\Http\Kernel;

/**
 * Binds the cache layer: the driver registry and factory, the stores,
 * the content version, the content cache, rendered bodies, and the page
 * cache, which runs among the kernel's middleware after `ConditionalGet`.
 * The content version moves on whenever the index is stored.
 *
 * An extension replaces the body cache by binding its own `BodyCache`,
 * and adds a driver to the registry.
 */
final class CacheServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		CacheDriverFactory::class,
		Caches::class,
		ContentVersion::class,
		ContentCache::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS_IF = [
		BodyCache::class => RenderedBodies::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TRANSIENTS = [
		PageCache::class,
		BumpContentVersion::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TAGS = [
		Kernel::MIDDLEWARE => [PageCache::class]
	];

	/**
	 * Binds the driver registry, seeded with the built-ins.
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singleton(
			CacheDriverRegistry::class,
			static function (): CacheDriverRegistry {
				$registry = new CacheDriverRegistry();
				new CacheDriverRegistrar($registry)->register();

				return $registry;
			}
		);
	}

	/**
	 * Moves the content version on when the index changes.
	 */
	#[Override]
	public function boot(): void
	{
		if ($this->container->has(ListenerRegistry::class)) {
			$this->container->make(ListenerRegistry::class)->listen(ContentIndexed::class, BumpContentVersion::class);
		}
	}
}
