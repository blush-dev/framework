<?php

/**
 * Invokable controller fixture.
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Routing;

use Psr\Http\Message\ResponseInterface;
use Blush\Http\Response;

final readonly class Page
{
	public function __invoke(string $name = 'page'): ResponseInterface
	{
		return Response::text("page:{$name}");
	}
}
