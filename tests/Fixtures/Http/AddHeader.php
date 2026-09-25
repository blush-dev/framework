<?php

/**
 * Header-adding middleware fixture.
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class AddHeader implements MiddlewareInterface
{
	public function __construct(private string $value = 'outer')
	{
	}

	#[Override]
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		$response = $handler->handle($request->withAttribute('seen', [...(array) $request->getAttribute('seen', []), $this->value]));

		return $response->withAddedHeader('X-Trace', $this->value);
	}
}
