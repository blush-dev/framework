<?php

/**
 * Field registrar.
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
 * Seeds the registry with the built-in field types, leaving any key
 * already registered alone.
 */
final readonly class FieldRegistrar
{
	public function __construct(private FieldRegistry $registry)
	{}

	/**
	 * Registers each built-in type whose key is still free.
	 *
	 * @throws RegistrationException
	 */
	public function register(): void
	{
		foreach (FieldType::cases() as $type) {
			$this->registry->registerIf($type->value, $type->className());
		}
	}
}
