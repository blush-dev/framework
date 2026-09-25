<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container;

use Blush\Core\Bootable;

final class FirstBoot implements Bootable
{
	public function __construct(private readonly Recorder $recorder)
	{}

	public function boot(): void
	{
		$this->recorder->events[] = 'first';
	}
}
