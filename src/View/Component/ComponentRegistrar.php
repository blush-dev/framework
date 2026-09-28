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
 * Seeds the component registry with the core components, leaving any a
 * provider has already registered alone.
 */
final readonly class ComponentRegistrar
{
	public function __construct(private ComponentRegistry $registry)
	{}

	/**
	 * Registers each core component whose name is still free.
	 *
	 * @throws RegistrationException
	 */
	public function register(): void
	{
		foreach (ComponentType::cases() as $type) {
			$this->registry->registerIf((string) $type->componentName(), $type->className(), $type->content(), $type->props());
		}
	}
}
