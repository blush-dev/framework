<?php

/**
 * Template-only component.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component;

/**
 * The `$component` of a component with no class of its own (D-195): its
 * template reads its props with `$component->prop('size', 'small')` and
 * prints its root with `$component->attributes()`, as a class
 * component's does. Give a component a class for typed props.
 */
final class TemplateComponent extends Component
{
}
