<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Plugin;

use Override;
use Blush\Core\ServiceProvider;

final class ComposerPluginProvider extends ServiceProvider
{
	#[Override]
	public function register(): void
	{
		$this->container->instance('composer-plugin.registered', true);
	}
}
