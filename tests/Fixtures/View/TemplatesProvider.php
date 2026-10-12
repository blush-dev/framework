<?php

/**
 * Entry templates provider fixture.
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
use Blush\View\EntryTemplate;
use Blush\View\EntryTemplateRegistry;

/**
 * Declares an entry template the chain has a view for and one it
 * doesn't, as a theme's provider would.
 */
final class TemplatesProvider extends ServiceProvider
{
	#[Override]
	public function boot(): void
	{
		$templates = $this->container->make(EntryTemplateRegistry::class);
		$templates->register(new EntryTemplate('single', 'Plain'));
		$templates->register(new EntryTemplate('template-gone', 'Gone'));
	}
}
