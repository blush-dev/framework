<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Event;

use Blush\Event\Named;
use Blush\Event\NamedEvent;
use Blush\Event\Stoppable;
use Blush\Event\StoppableEvent;

final class OrderPlaced extends BaseEvent implements Auditable, NamedEvent, StoppableEvent
{
	use Named;
	use Stoppable;

	public const string NAME = 'order.placed';

	/** @var list<string> */
	public array $calls = [];
}
