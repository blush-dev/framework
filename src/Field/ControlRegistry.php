<?php

/**
 * Control registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

use Blush\Support\Registry;

/**
 * Maps control keys to `Control` classes. Only core registers controls
 * for 2.x (D-766); the key must match what the class's `name()` returns.
 *
 * @extends Registry<Control>
 */
final class ControlRegistry extends Registry
{
	/**
	 * @inheritDoc
	 */
	protected const string CONTRACT = Control::class;
}
