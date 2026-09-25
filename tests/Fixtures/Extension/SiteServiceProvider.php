<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Extension;

use Override;
use Blush\Core\ServiceProvider;

final class SiteServiceProvider extends ServiceProvider
{
	#[Override]
	public function boot(): void
	{
		$this->container->instance('site.booted', true);
	}
}
