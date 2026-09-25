<?php

/**
 * Kernel event recorder fixture.
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Http;

use Blush\Http\Events\RequestReceived;
use Blush\Http\Events\ResponseReady;

final class ResponseRecorder
{
	/**
	 * @var list<string>
	 */
	public array $events = [];

	public function received(RequestReceived $event): void
	{
		$this->events[] = 'received ' . $event->request->getRequestTarget();
	}

	public function ready(ResponseReady $event): void
	{
		$this->events[] = 'ready ' . $event->response->getStatusCode();
	}
}
