<?php

/**
 * Field set source.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

/**
 * Supplies field sets from an extension (D-337). An extension tags its
 * source with `FieldSetSource::TAG` in a provider's `TAGS`. A site's
 * `config/fields.php` or `user/data/fields` can redefine any set an
 * extension adds, by its name.
 */
interface FieldSetSource
{
	/**
	 * The container tag for field set sources.
	 */
	public const string TAG = 'field.sets';

	/**
	 * Returns the sets.
	 *
	 * @return iterable<FieldSet>
	 */
	public function fieldSets(): iterable;
}
