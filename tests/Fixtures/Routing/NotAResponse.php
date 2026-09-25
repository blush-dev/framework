<?php

/**
 * Controller fixture returning the wrong type.
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Routing;

final readonly class NotAResponse
{
	public function __invoke(): string
	{
		return 'oops';
	}
}
