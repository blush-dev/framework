<?php

/**
 * View engine.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View\Engine;

use Throwable;
use Blush\View\Template;

/**
 * Turns one template file into HTML (D-502). `Views` picks the engine by
 * the file's extension, so every template (a page, layout, partial, or
 * component) goes through one, and engines can mix in a theme chain: a
 * Twig layout can include a PHP partial, since `include()` goes back
 * through `Views`.
 *
 * An engine gets the template's data and its `Template`, the API every
 * engine exposes (as `$template` in PHP; an adapter picks its own name,
 * such as a `template` global). What a template asks for (a layout,
 * sections) goes on the `Template`; `Views` renders the layout after.
 * Methods marked `#[ReturnsHtml]`, and values that are `SafeHtml`, are
 * rendered HTML an engine that escapes on its own must print as they
 * are.
 */
interface ViewEngine
{
	/**
	 * Renders a template file with its data.
	 *
	 * @param  array<string, mixed> $data
	 * @throws Throwable Anything the template throws; `Views` names the file.
	 */
	public function render(string $file, array $data, Template $template): string;
}
