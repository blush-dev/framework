<?php

/**
 * Nova component provider fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\View;

use Override;
use Blush\Core\ServiceProvider;
use Blush\View\Component\ComponentRegistry;

final class NovaProvider extends ServiceProvider
{
	#[Override]
	public function boot(): void
	{
		$this->container->make(ComponentRegistry::class)->register('nova/badge');
	}
}
