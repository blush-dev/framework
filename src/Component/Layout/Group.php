<?php

/**
 * Group component.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component\Layout;

use Override;
use Blush\Component\ComponentView;
use Blush\Core\Framework;

/**
 * Wraps blocks so they can be styled together (D-175, D-177, D-298):
 * `:::group{.alignwide}` … `:::`. It renders a `<div>`, or a `<section>`
 * or `<aside>` with `tag`, where the label becomes its accessible name.
 * Its `class` and `id` come from the directive's `.class` and `#id`.
 */
final class Group extends Layout
{
	public function __construct(
		public readonly LayoutTag $tag = LayoutTag::Div,
		public readonly string $label = ''
	) {}

	/**
	 * Renders the framework's template for it, `resources/components/group.php`
	 * (D-382), when the theme chain has none of its own.
	 */
	#[Override]
	public function render(): ComponentView
	{
		return $this->view(Framework::path('resources/components/group.php'));
	}
}
