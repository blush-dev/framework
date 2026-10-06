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

namespace Blush\Directive;

/**
 * Renders Markdown directives (D-026). The directive layer binds
 * `Directive\MarkdownDirectives`, which maps each directive to the
 * registered directive of the same name (D-532). Returning `null` means the directive is unknown, and
 * it renders as plain content, never as an error.
 */
interface DirectiveRenderer
{
	/**
	 * Returns a directive's HTML, or `null` when it's unknown.
	 */
	public function render(ParsedDirective $directive): ?string;
}
