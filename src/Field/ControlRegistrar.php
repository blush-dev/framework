<?php

/**
 * Control registrar.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

use Blush\Support\RegistrationException;

/**
 * Seeds the registry with the core controls, leaving any key already
 * registered alone.
 */
final readonly class ControlRegistrar
{
	public function __construct(private ControlRegistry $registry)
	{}

	/**
	 * Registers each core control whose key is still free.
	 *
	 * @throws RegistrationException
	 */
	public function register(): void
	{
		foreach (ControlType::cases() as $type) {
			$this->registry->registerIf($type->value, $type->className());
		}
	}
}
