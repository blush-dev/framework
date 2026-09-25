<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Event;

use Blush\Event\BroadcastableEvent;

final class Broadcast implements BroadcastableEvent
{
	public int $broadcasts = 0;

	public function broadcast(): static
	{
		$this->broadcasts++;

		return $this;
	}
}
