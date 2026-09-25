<?php

/**
 * Throwing middleware fixture.
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Http;

use Override;
use RuntimeException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class Failing implements MiddlewareInterface
{
	#[Override]
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		throw new RuntimeException('Middleware exploded <b>here</b>.');
	}
}
