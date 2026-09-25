<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Event;

use Blush\Event\Listener\Attributes\Listen;
use Blush\Event\Listener\Attributes\ListenOnce;
use Blush\Event\Listener\Attributes\ListenTo;
use Blush\Event\Listener\Attributes\ListenToUntil;
use Blush\Event\Listener\DiscoversListeners;
use Blush\Event\Listener\ListenerPriority;
use Blush\Event\Listener\ListenerSubscriber;

final class OrderSubscriber implements ListenerSubscriber
{
	use DiscoversListeners;

	#[ListenTo(priority: ListenerPriority::Last)]
	public function last(OrderPlaced $event): void
	{
		$event->calls[] = 'last';
	}

	#[Listen(OrderPlaced::NAME, priority: ListenerPriority::First)]
	public function first(OrderPlaced $event): void
	{
		$event->calls[] = 'first';
	}

	#[ListenOnce(OrderPlaced::class)]
	public function once(OrderPlaced $event): void
	{
		$event->calls[] = 'once';
	}

	#[ListenToUntil(static function (OrderPlaced $event): bool {
		return count($event->calls) > 1;
	})]
	public function until(OrderPlaced $event): void
	{
		$event->calls[] = 'until';
	}
}
