<?php

/**
 * Block view.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Block;

/**
 * A template file a block ships with, which its `render()` returns
 * (D-382): rendered as a theme's block template is, with `$template`,
 * `$block`, and `$data`'s variables. It's the block's own markup,
 * used when no theme in the chain (and not the site) has a template for
 * it, so a plugin's blocks still render in any theme.
 */
final readonly class BlockView
{
	/**
	 * @param string               $file The template's absolute path.
	 * @param array<string, mixed> $data More variables for the template.
	 */
	public function __construct(
		public string $file,
		public array $data = []
	) {}
}
