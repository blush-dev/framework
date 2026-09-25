<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container;

/**
 * Constructor of built-in-typed parameters the container cannot autowire, so
 * they must be supplied by a contextual `whenNeedsParam()` binding (or a
 * `make()` override).
 */
final class NeedsApiKey
{
	public function __construct(
		public readonly string $apiKey,
		public readonly int $timeout
	) {}
}
