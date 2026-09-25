<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Extension;

use Override;
use Blush\Core\ServiceProvider;

final class ComposerExtensionProvider extends ServiceProvider
{
	#[Override]
	public function register(): void
	{
		$this->container->instance('composer-ext.registered', true);
	}
}
