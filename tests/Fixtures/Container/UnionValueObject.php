<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container;

/**
 * A consumer whose dependency is a union of an un-autowirable value object
 * and an autowirable class. Building the value object throws, so the union
 * must fall through to the buildable alternative.
 */
final class UnionValueObject
{
	public function __construct(public readonly ValueObject|FileCache $dep)
	{}
}
