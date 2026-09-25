<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Event;

final class InvokableListener
{
	public static int $instances = 0;

	public function __construct()
	{
		self::$instances++;
	}

	public function __invoke(OrderPlaced $event): void
	{
		$event->calls[] = 'invokable';
	}
}
