<?php

/**
 * Provider registrar.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Embed;

use Blush\Support\RegistrationException;

/**
 * Seeds the registry with the built-in providers, leaving any name an
 * extension registered first alone.
 */
final readonly class ProviderRegistrar
{
	public function __construct(private ProviderRegistry $registry)
	{}

	/**
	 * Registers each built-in provider whose name is still free.
	 *
	 * @throws RegistrationException
	 */
	public function register(): void
	{
		foreach (ProviderType::cases() as $type) {
			$this->registry->registerIf($type->value, $type->className());
		}
	}
}
