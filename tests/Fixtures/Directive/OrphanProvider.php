<?php

/**
 * Orphan directive provider fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Directive;

use Override;
use Blush\Core\ServiceProvider;
use Blush\Directive\DirectiveRegistry;

final class OrphanProvider extends ServiceProvider
{
	#[Override]
	public function boot(): void
	{
		$this->container->make(DirectiveRegistry::class)->register('acme/orphan', Orphan::class);
	}
}
