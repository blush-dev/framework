<?php

/**
 * Directive renderer interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown;

/**
 * Renders Markdown directives (D-026). The view layer binds
 * `View\ComponentDirectives`, which maps each directive to the component
 * of the same name. Returning `null` means the directive is unknown, and
 * it renders as plain content, never as an error.
 */
interface DirectiveRenderer
{
	/**
	 * Returns a directive's HTML, or `null` when it's unknown.
	 */
	public function render(Directive $directive): ?string;
}
