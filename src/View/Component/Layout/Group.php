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

namespace Blush\View\Component\Layout;

use Blush\View\Component\Component;
use Blush\View\Component\ComponentContent;

/**
 * Wraps blocks so they can be styled together (D-175, D-177):
 * `:::group{.alignwide}` … `:::`. It renders a `<div>`, or a `<section>`
 * with `tag=section`, where the label becomes its accessible name. Its
 * `class` and `id` come from the directive's `.class` and `#id`.
 */
final class Group extends Component
{
	/**
	 * @inheritDoc
	 */
	public const ComponentContent CONTENT = ComponentContent::Blocks;

	public function __construct(
		public readonly GroupTag $tag = GroupTag::Div,
		public readonly string $label = ''
	) {}
}
