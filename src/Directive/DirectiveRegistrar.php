<?php

/**
 * Directive registrar.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Directive;

use Blush\Support\RegistrationException;

/**
 * Seeds the directive registry with the core directives, leaving any a
 * provider has already registered alone.
 */
final readonly class DirectiveRegistrar
{
	public function __construct(private DirectiveRegistry $registry)
	{}

	/**
	 * Registers each core directive whose name is still free.
	 *
	 * @throws RegistrationException
	 */
	public function register(): void
	{
		foreach (DirectiveType::cases() as $type) {
			$this->registry->registerIf((string) $type->directiveName(), $type->className());
		}
	}
}
