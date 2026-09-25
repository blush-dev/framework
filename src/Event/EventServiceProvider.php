<?php

/**
 * Event service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Event;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Blush\Container\ServiceResolver;
use Blush\Core\ServiceProvider;
use Blush\Event\Listener\Listenable;
use Blush\Event\Listener\ListenerProvider;
use Blush\Event\Listener\ListenerRegistry;
use Blush\Event\Listener\Subscribable;

/**
 * Wires the event system into the container. One shared `ListenerRegistry`
 * serves as the listener provider and the place listeners are registered, and
 * listeners registered by class name are built through the container, so they
 * can type-hint their own dependencies.
 */
final class EventServiceProvider extends ServiceProvider
{
	/**
	 * The dispatcher, shared for the application.
	 */
	protected const array SINGLETONS = [
		Dispatcher::class => EventDispatcher::class
	];

	/**
	 * The registry answers for every listener-side interface, and the
	 * dispatcher for the PSR-14 one.
	 */
	protected const array ALIASES = [
		ListenerProvider::class          => ListenerRegistry::class,
		Listenable::class                => ListenerRegistry::class,
		Subscribable::class              => ListenerRegistry::class,
		ListenerProviderInterface::class => ListenerRegistry::class,
		EventDispatcherInterface::class  => Dispatcher::class
	];

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singleton(
			ListenerRegistry::class,
			static fn (ServiceResolver $resolver): ListenerRegistry => new ListenerRegistry(
				static fn (string $class): object => $resolver->make($class)
			)
		);
	}
}
