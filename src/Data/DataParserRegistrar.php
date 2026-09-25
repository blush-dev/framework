<?php

/**
 * Data parser registrar.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Data;

use Blush\Support\RegistrationException;

/**
 * Seeds the registry with the built-in formats, leaving any extension
 * already registered alone.
 */
final readonly class DataParserRegistrar
{
	public function __construct(private DataParserRegistry $registry)
	{}

	/**
	 * Registers each built-in format whose extension is still free.
	 *
	 * @throws RegistrationException
	 */
	public function register(): void
	{
		foreach (DataFormat::cases() as $format) {
			$this->registry->registerIf($format->value, $format->parser());
		}
	}
}
