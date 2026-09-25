<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container;

use Blush\Core\ServiceProvider;

final class BootableProvider extends ServiceProvider
{
	protected const array BOOTABLE = [
		FirstBoot::class,
		SecondBoot::class
	];
}
