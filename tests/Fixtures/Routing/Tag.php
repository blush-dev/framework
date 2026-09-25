<?php

/**
 * Route middleware fixture.
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Routing;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class Tag implements MiddlewareInterface
{
	#[Override]
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		return $handler->handle($request)->withAddedHeader('X-Route', 'tagged');
	}
}
