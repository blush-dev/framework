<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container;

/**
 * A value object that mirrors a class like an entry: it exists, so
 * class_exists() is true, but its required, scalar constructor parameter
 * means the container cannot autowire it.
 */
final class ValueObject
{
	public function __construct(public readonly string $source)
	{
	}
}
