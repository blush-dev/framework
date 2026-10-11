<?php

/**
 * Tool registrar.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tool;

use Blush\Support\RegistrationException;

/**
 * Seeds the registry with Blush's own tools.
 */
final readonly class ToolRegistrar
{
	public function __construct(private ToolRegistry $registry)
	{}

	/**
	 * Registers each built-in tool whose key is still free.
	 *
	 * @throws RegistrationException
	 */
	public function register(): void
	{
		foreach (ToolType::cases() as $type) {
			$this->registry->registerIf($type->value, $type->className());
		}
	}
}
