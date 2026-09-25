<?php

/**
 * Component registrar.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View\Component;

use Blush\Support\RegistrationException;

/**
 * Seeds the component registry with the built-in classes, leaving any key
 * a provider has already registered alone.
 */
final readonly class ComponentRegistrar
{
	public function __construct(private ComponentRegistry $registry)
	{}

	/**
	 * Registers each built-in component whose key is still free.
	 *
	 * @throws RegistrationException
	 */
	public function register(): void
	{
		foreach (ComponentType::cases() as $type) {
			$this->registry->registerIf($type->value, $type->className());
		}
	}
}
