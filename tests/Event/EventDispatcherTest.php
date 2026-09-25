<?php

/**
 * Event dispatcher tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Event;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Container\ServiceContainer;
use Blush\Core\Application;
use Blush\Core\Events\ApplicationBooted;
use Blush\Event\Dispatcher;
use Blush\Event\EventDispatcher;
use Blush\Event\EventException;
use Blush\Event\EventServiceProvider;
use Blush\Event\Listener\InvalidListener;
use Blush\Event\Listener\Listenable;
use Blush\Event\Listener\ListenerPriority;
use Blush\Event\Listener\ListenerRegistry;
use Blush\Event\Listener\NotInvokable;
use Blush\Tests\Fixtures\Event\Auditable;
use Blush\Tests\Fixtures\Event\BaseEvent;
use Blush\Tests\Fixtures\Event\Broadcast;
use Blush\Tests\Fixtures\Event\InvokableListener;
use Blush\Tests\Fixtures\Event\NotInvokableListener;
use Blush\Tests\Fixtures\Event\OrderPlaced;
use Blush\Tests\Fixtures\Event\OrderSubscriber;

#[CoversClass(EventDispatcher::class)]
#[CoversClass(ListenerRegistry::class)]
#[CoversClass(EventServiceProvider::class)]
final class EventDispatcherTest extends TestCase
{
	private ListenerRegistry $registry;

	private EventDispatcher $dispatcher;

	protected function setUp(): void
	{
		$this->registry   = new ListenerRegistry();
		$this->dispatcher = new EventDispatcher($this->registry);
	}

	/**
	 * Returns a listener that records `$name` on the event.
	 *
	 * @return callable(OrderPlaced): void
	 */
	private function recorder(string $name): callable
	{
		return static function (OrderPlaced $event) use ($name): void {
			$event->calls[] = $name;
		};
	}

	public function testDispatchReturnsTheSameEvent(): void
	{
		$event = new OrderPlaced();

		$this->assertSame($event, $this->dispatcher->dispatch($event));
	}

	public function testListenersRunByPriorityThenRegistrationOrder(): void
	{
		$this->registry->listen(OrderPlaced::class, $this->recorder('b'));
		$this->registry->listen(OrderPlaced::class, $this->recorder('c'));
		$this->registry->listen(OrderPlaced::class, $this->recorder('a'), -10);
		$this->registry->listen(OrderPlaced::class, $this->recorder('z'), ListenerPriority::Last);
		$this->registry->listen(OrderPlaced::class, $this->recorder('first'), ListenerPriority::First);

		$event = $this->dispatcher->dispatch(new OrderPlaced());

		$this->assertSame(['first', 'a', 'b', 'c', 'z'], $event->calls);
	}

	public function testListenersMatchParentsInterfacesAndNames(): void
	{
		$this->registry->listen(BaseEvent::class, $this->recorder('parent'));
		$this->registry->listen(Auditable::class, $this->recorder('interface'));
		$this->registry->listen(OrderPlaced::NAME, $this->recorder('name'));

		$event = $this->dispatcher->dispatch(new OrderPlaced());

		$this->assertSame(['parent', 'interface', 'name'], $event->calls);
		$this->assertTrue($this->registry->hasListeners(OrderPlaced::class));
		$this->assertFalse($this->registry->hasListeners(Broadcast::class));
	}

	public function testStoppedPropagationSkipsLaterListeners(): void
	{
		$this->registry->listen(OrderPlaced::class, static function (OrderPlaced $event): void {
			$event->calls[] = 'stopper';
			$event->stopPropagation();
		});
		$this->registry->listen(OrderPlaced::class, $this->recorder('skipped'));

		$event = $this->dispatcher->dispatch(new OrderPlaced());

		$this->assertSame(['stopper'], $event->calls);
	}

	public function testListenOnceFiresOnce(): void
	{
		$this->registry->listenOnce(OrderPlaced::class, $this->recorder('once'));

		$this->dispatcher->dispatch(new OrderPlaced());

		$this->assertSame([], $this->dispatcher->dispatch(new OrderPlaced())->calls);
	}

	public function testListenUntilStopsWhenConditionHolds(): void
	{
		$count = 0;

		$this->registry->listenUntil(
			OrderPlaced::class,
			$this->recorder('until'),
			static function () use (&$count): bool {
				return ++$count >= 2;
			}
		);

		$first  = $this->dispatcher->dispatch(new OrderPlaced());
		$second = $this->dispatcher->dispatch(new OrderPlaced());
		$third  = $this->dispatcher->dispatch(new OrderPlaced());

		$this->assertSame(['until'], $first->calls);
		$this->assertSame(['until'], $second->calls);
		$this->assertSame([], $third->calls);
	}

	public function testListenToDerivesTheEventType(): void
	{
		$this->registry->listenTo($this->recorder('derived'));

		$this->assertSame(['derived'], $this->dispatcher->dispatch(new OrderPlaced())->calls);
	}

	public function testListenToRejectsUntypedListeners(): void
	{
		$this->expectException(InvalidListener::class);

		$this->registry->listenTo(static function ($event): void {
		});
	}

	public function testClassNameListenersAreBuiltLazilyOnce(): void
	{
		InvokableListener::$instances = 0;

		$this->registry->listen(OrderPlaced::class, InvokableListener::class);
		$this->assertSame(0, InvokableListener::$instances);

		$this->dispatcher->dispatch(new OrderPlaced());
		$event = $this->dispatcher->dispatch(new OrderPlaced());

		$this->assertSame(['invokable'], $event->calls);
		$this->assertSame(1, InvokableListener::$instances);
	}

	public function testNonInvokableListenerClassThrows(): void
	{
		$this->registry->listen(OrderPlaced::class, NotInvokableListener::class);

		$this->expectException(NotInvokable::class);

		$this->dispatcher->dispatch(new OrderPlaced());
	}

	public function testInvalidListenerStringThrows(): void
	{
		$this->expectException(EventException::class);

		$this->registry->listen(OrderPlaced::class, 'not a callable');
	}

	public function testForgetAndForgetId(): void
	{
		$listener = $this->recorder('kept');

		$id = $this->registry->listen(OrderPlaced::class, $this->recorder('by-id'));
		$this->registry->listen(OrderPlaced::class, $listener);
		$this->registry->forgetId($id);

		$this->assertSame(['kept'], $this->dispatcher->dispatch(new OrderPlaced())->calls);

		$this->registry->forget(OrderPlaced::class, $listener);

		$this->assertSame([], $this->dispatcher->dispatch(new OrderPlaced())->calls);
	}

	public function testSubscriberAttributesAndUnsubscribe(): void
	{
		$subscriber = new OrderSubscriber();
		$this->registry->subscribe($subscriber);

		$this->assertSame(
			['first', 'once', 'until', 'last'],
			$this->dispatcher->dispatch(new OrderPlaced())->calls
		);

		// `once` is gone. `until` saw two calls already on the event the
		// first time, so that delivery was its last.
		$this->assertSame(
			['first', 'last'],
			$this->dispatcher->dispatch(new OrderPlaced())->calls
		);

		$this->registry->unsubscribe($subscriber);

		$this->assertSame([], $this->dispatcher->dispatch(new OrderPlaced())->calls);
	}

	public function testBroadcastChainsOntoDispatch(): void
	{
		$event = $this->dispatcher->dispatch(new Broadcast())->broadcast();

		$this->assertSame(1, $event->broadcasts);
	}

	public function testServiceProviderWiresTheRegistryAndDispatcher(): void
	{
		$container = new ServiceContainer();
		$app       = new Application($container);
		$app->register(EventServiceProvider::class);

		$registry = $container->make(Listenable::class);
		$booted   = [];

		$registry->listen(ApplicationBooted::class, static function (ApplicationBooted $event) use (&$booted): void {
			$booted[] = $event->application;
		});
		$registry->listen(OrderPlaced::class, InvokableListener::class);

		$app->boot();
		$app->boot();

		$this->assertSame([$app], $booted);
		$this->assertSame($registry, $container->get(ListenerProviderInterface::class));
		$this->assertSame($container->get(Dispatcher::class), $container->get(EventDispatcherInterface::class));
		$this->assertSame(
			['invokable'],
			$container->make(Dispatcher::class)->dispatch(new OrderPlaced())->calls
		);
	}
}
