<?php

/**
 * View engine registrar.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View\Engine;

use Blush\Support\RegistrationException;

/**
 * Seeds the registry with the built-in engines, leaving any extension an
 * extension registered first alone.
 */
final readonly class ViewEngineRegistrar
{
	public function __construct(private ViewEngineRegistry $registry)
	{}

	/**
	 * Registers each built-in engine whose extension is still free.
	 *
	 * @throws RegistrationException
	 */
	public function register(): void
	{
		foreach (ViewEngineType::cases() as $type) {
			$this->registry->registerIf($type->value, $type->engine());
		}
	}
}
