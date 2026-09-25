<?php

/**
 * Short-circuiting middleware fixture.
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Blush\Http\Response;

final readonly class ShortCircuit implements MiddlewareInterface
{
	#[Override]
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		return Response::text('short-circuited', 403);
	}
}
