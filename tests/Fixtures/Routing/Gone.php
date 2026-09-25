<?php

/**
 * Controller fixture that finds nothing.
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Routing;

use Psr\Http\Message\ResponseInterface;
use Blush\Http\NotFound;

final readonly class Gone
{
	public function __invoke(): ResponseInterface
	{
		throw new NotFound('Nothing here.');
	}
}
