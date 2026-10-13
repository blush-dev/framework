<?php

/**
 * Block renderer interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Block;

/**
 * Renders Markdown blocks (D-026). The block layer binds
 * `Block\RegisteredBlocks`, which maps each block to the
 * registered block of the same name (D-532). Returning `null` means the block is unknown, and
 * it renders as plain content, never as an error.
 */
interface BlockRenderer
{
	/**
	 * Returns a block's HTML, or `null` when it's unknown.
	 */
	public function render(ParsedBlock $block): ?string;
}
