<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container;

enum Status: string
{
	case Active = 'active';
	case Inactive = 'inactive';
}
