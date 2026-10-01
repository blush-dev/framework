<?php

/**
 * Embedded reader registrar.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media\Embedded;

/**
 * Seeds the registry with the built-in readers, without replacing any an
 * extension registered first.
 */
final readonly class EmbeddedReaderRegistrar
{
	public function __construct(private EmbeddedReaderRegistry $registry)
	{}

	public function register(): void
	{
		foreach (EmbeddedReaderType::cases() as $type) {
			$this->registry->registerIf($type->value, $type->className());
		}
	}
}
