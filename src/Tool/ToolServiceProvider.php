<?php

/**
 * Tool service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tool;

use Override;
use Blush\Core\ServiceProvider;

/**
 * Binds tools (D-761): the registry, seeded with Blush's own, running
 * them, and the contexts they run in.
 *
 * A plugin adds a tool in its own provider's `boot()`:
 *
 *     $this->container->make(ToolRegistry::class)->register('acme/rebuild-thumbnails', RebuildThumbnails::class);
 */
final class ToolServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		Tools::class,
		ToolContexts::class,
		ToolEntries::class
	];

	#[Override]
	public function register(): void
	{
		$this->container->singleton(ToolRegistry::class, static function (): ToolRegistry {
			$registry = new ToolRegistry();
			new ToolRegistrar($registry)->register();

			return $registry;
		});
	}
}
