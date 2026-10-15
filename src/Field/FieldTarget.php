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

use Blush\Translation\Text;

/**
 * A place fields go on (D-337), such as a content type: the fields code
 * registers for it, and the library fields its layout places (D-858).
 * Field types know nothing about where they're used; a target says which
 * fields it takes, so one that can't store a kind of value (a settings
 * screen with no index for a reference to read, say) refuses it.
 */
interface FieldTarget
{
	/**
	 * Returns the key it's named by: a kind, a colon, and a name, such as
	 * `type:post` (`TargetKey`).
	 */
	public function key(): string;

	/**
	 * Returns the target's name for people, written with `t()` when it's
	 * core's text rather than the site's (D-836).
	 */
	public function label(): string|Text;

	/**
	 * Returns whether the target takes a field.
	 */
	public function accepts(Field $field): bool;

	/**
	 * Returns the target's own fields, before any it's given: the schema
	 * `FieldLibrary::schemaFor()` adds registered and library fields to.
	 *
	 * @throws InvalidSchema When the target's own fields don't fit together.
	 */
	public function schema(): Schema;

	/**
	 * Returns the library fields it carries (D-858), in its sections;
	 * none for a place without a layout of its own yet.
	 */
	public function layout(): FieldLayout;
}
