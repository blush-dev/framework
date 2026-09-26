<?php

/**
 * Host files registrar.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export\Host;

use Blush\Support\RegistrationException;

/**
 * Seeds the registry with the built-in formats, leaving any name an
 * extension registered first alone.
 */
final readonly class HostFilesRegistrar
{
	public function __construct(private HostFilesRegistry $registry)
	{}

	/**
	 * Registers each built-in format whose name is still free.
	 *
	 * @throws RegistrationException
	 */
	public function register(): void
	{
		foreach (HostFormat::cases() as $format) {
			$this->registry->registerIf($format->value, $format->className());
		}
	}
}
