<?php

/**
 * Field registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Schema;

use Blush\Support\Registry;

/**
 * Maps field type keys to `Field` classes. It starts with the built-in
 * types; an extension adds one (say, `color`) by registering it in a
 * `resolving()` callback. The key must match what the class's `type()`
 * returns.
 *
 * @extends Registry<Field>
 */
final class FieldRegistry extends Registry
{
	/**
	 * @inheritDoc
	 */
	protected const string CONTRACT = Field::class;
}
