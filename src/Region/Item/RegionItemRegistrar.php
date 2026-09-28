<?php

/**
 * Region item registrar.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Region\Item;

use Blush\Support\RegistrationException;

/**
 * Seeds the registry with the built-in item kinds, leaving any key an
 * extension registered first alone.
 */
final readonly class RegionItemRegistrar
{
	public function __construct(private RegionItemRegistry $registry)
	{}

	/**
	 * Registers each built-in kind whose key is still free.
	 *
	 * @throws RegistrationException
	 */
	public function register(): void
	{
		foreach (RegionItemType::cases() as $type) {
			$this->registry->registerIf($type->value, $type->className());
		}
	}
}
