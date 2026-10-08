<?php

/**
 * Orphan component provider fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Component;

use Override;
use Blush\Component\ComponentRegistry;
use Blush\Core\ServiceProvider;

/**
 * Registers a component class that has no template.
 */
final class OrphanProvider extends ServiceProvider
{
	#[Override]
	public function boot(): void
	{
		$this->container->make(ComponentRegistry::class)->register('acme/orphan', Orphan::class);
	}
}
