<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container;

use Blush\Container\Attributes\Build;

/**
 * Points `#[MakeFresh]` at an identifier that is neither registered nor
 * buildable, used to verify the failure surfaces cleanly.
 */
final class MakeFreshUnknownConsumer
{
	public function __construct(
		// @phpstan-ignore argument.type (the missing class is the point)
		#[Build('Blush\Tests\Fixtures\Container\DoesNotExist')]
		public readonly object $thing
	) {}
}
