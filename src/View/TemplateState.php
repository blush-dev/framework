<?php

/**
 * Template state.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

/**
 * What a template left for `Views` to act on after it ran (D-815): the
 * layout it asked for, and the sections it's still capturing. Kept
 * apart from `Template` so a template's API is only what templates
 * call.
 *
 * @internal
 */
final class TemplateState
{
	/**
	 * The layout the template asked for, and its data.
	 *
	 * @var ?array{string, array<string, mixed>}
	 */
	public ?array $layout = null;

	/**
	 * Sections being captured, innermost last.
	 *
	 * @var list<string>
	 */
	public array $capturing = [];
}
