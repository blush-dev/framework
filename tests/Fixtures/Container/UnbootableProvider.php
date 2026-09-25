<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container;

use Blush\Core\ServiceProvider;

final class UnbootableProvider extends ServiceProvider
{
	protected const array BOOTABLE = [
		SharedService::class
	];
}
