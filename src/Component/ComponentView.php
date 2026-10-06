<?php

/**
 * Component view.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component;

/**
 * A template file a component ships with, which its `render()` returns
 * (D-382): rendered as a theme's component template is, with `$template`,
 * `$component`, and `$data`'s variables. It's the component's own markup,
 * used when no theme in the chain (and not the site) has a template for
 * it, so a plugin's components still render in any theme.
 */
final readonly class ComponentView
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
