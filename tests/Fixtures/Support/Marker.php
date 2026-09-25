<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Support;

use Attribute;

#[Attribute(Attribute::TARGET_ALL | Attribute::IS_REPEATABLE)]
final class Marker implements Label
{
	public function __construct(public readonly string $name)
	{
	}
}
