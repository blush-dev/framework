<?php

/**
 * Component service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component;

use Override;
use Blush\Core\ServiceProvider;
use Blush\Markdown\DirectiveRenderer;

/**
 * Binds components (D-025): the registry, seeded with the built-ins, the
 * factory, and the Markdown directive renderer, a default an extension
 * can replace by binding its own.
 *
 * A theme or site provider registers a component in `boot()`:
 *
 *     $this->container->make(ComponentRegistry::class)->register('app/card', Card::class);
 */
final class ComponentServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		ComponentFactory::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS_IF = [
		DirectiveRenderer::class => ComponentDirectives::class
	];

	/**
	 * Binds the component registry, seeded with the built-ins.
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singleton(
			ComponentRegistry::class,
			static function (): ComponentRegistry {
				$registry = new ComponentRegistry();
				new ComponentRegistrar($registry)->register();

				return $registry;
			}
		);
	}
}
