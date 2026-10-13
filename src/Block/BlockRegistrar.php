<?php

/**
 * Block registrar.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Block;

use Blush\Support\RegistrationException;

/**
 * Seeds the block registry with the core blocks, leaving any a
 * provider has already registered alone.
 */
final readonly class BlockRegistrar
{
	public function __construct(private BlockRegistry $registry)
	{}

	/**
	 * Registers each core block whose name is still free.
	 *
	 * @throws RegistrationException
	 */
	public function register(): void
	{
		foreach (BlockType::cases() as $type) {
			$this->registry->registerIf((string) $type->blockName(), $type->className());
		}
	}
}
