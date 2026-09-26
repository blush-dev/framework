<?php

/**
 * Plain host provider fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Export;

use Override;
use Blush\Core\ServiceProvider;
use Blush\Export\Host\HostFilesRegistry;

final class PlainHostProvider extends ServiceProvider
{
	#[Override]
	public function boot(): void
	{
		$this->container->make(HostFilesRegistry::class)->register('plain', PlainHostFiles::class);
	}
}
