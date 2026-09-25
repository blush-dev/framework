<?php

/**
 * Echoing handler fixture.
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Blush\Http\Response;

final readonly class EchoHandler implements RequestHandlerInterface
{
	#[Override]
	public function handle(ServerRequestInterface $request): ResponseInterface
	{
		$seen = $request->getAttribute('seen', []);

		return Response::text(sprintf(
			'%s %s [%s]',
			$request->getMethod(),
			$request->getRequestTarget(),
			implode(',', is_array($seen) ? array_filter($seen, is_string(...)) : [])
		));
	}
}
