<?php

/**
 * Orphan block provider fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Block;

use Override;
use Blush\Core\ServiceProvider;
use Blush\Block\BlockRegistry;

final class OrphanProvider extends ServiceProvider
{
	#[Override]
	public function boot(): void
	{
		$this->container->make(BlockRegistry::class)->register('acme/orphan', Orphan::class);
	}
}
