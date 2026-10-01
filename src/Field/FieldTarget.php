<?php

/**
 * Field target.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

/**
 * A place field sets attach to (D-337), such as a content type. Field
 * types know nothing about where they're used; a target says which fields
 * it takes, so one that can't store a kind of value (a settings screen
 * with no index for a reference to read, say) refuses it.
 */
interface FieldTarget
{
	/**
	 * Returns the key sets name it by: a kind, a colon, and a name, such
	 * as `type:post`.
	 */
	public function key(): string;

	/**
	 * Returns the target's name for people.
	 */
	public function label(): string;

	/**
	 * Returns whether the target takes a field.
	 */
	public function accepts(Field $field): bool;
}
