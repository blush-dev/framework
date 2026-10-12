<?php

/**
 * Field source.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

/**
 * Registers fields (D-790). An extension tags its source with
 * `FieldSource::TAG` in a provider's `TAGS`, and each field it returns
 * is owned by the extension's `vendor/name`; core's sources are tagged
 * `FieldSource::CORE_TAG`, owned by `core`.
 */
interface FieldSource
{
	/**
	 * The container tag for extensions' field sources.
	 */
	public const string TAG = 'field.sources';

	/**
	 * The container tag for core's field sources.
	 */
	public const string CORE_TAG = 'field.sources.core';

	/**
	 * Returns the fields it registers.
	 *
	 * @return iterable<RegisteredField>
	 */
	public function fields(): iterable;
}
