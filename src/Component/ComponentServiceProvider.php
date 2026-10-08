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

use Blush\Core\ServiceProvider;

/**
 * Binds components (D-025, D-532): the registry of component classes. A
 * theme or plugin provider registers a class in `boot()`:
 *
 *     $this->container->make(ComponentRegistry::class)->register('acme/card', Card::class);
 */
final class ComponentServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		ComponentRegistry::class
	];
}
