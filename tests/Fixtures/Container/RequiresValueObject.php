<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container;

final class RequiresValueObject
{
	public function __construct(public readonly ValueObject $value)
	{}
}
