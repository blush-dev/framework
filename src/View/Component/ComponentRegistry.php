<?php

/**
 * Component registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View\Component;

use Blush\Support\Registry;

/**
 * Maps component keys to their classes. Template-only components need no
 * entry; their template is found by key through the view chain. A site or
 * theme provider registers a class to add one, or to replace a built-in
 * (`register()` overwrites; the built-ins are seeded with `registerIf()`).
 *
 * @extends Registry<Component>
 */
final class ComponentRegistry extends Registry
{
	/**
	 * @inheritDoc
	 */
	protected const string CONTRACT = Component::class;
}
