<?php

/**
 * Content type source.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

/**
 * Supplies content types from an extension (D-041). An extension tags its
 * source with `ContentTypeSource::TAG` in a provider's `TAGS`. A data type
 * in `user/data/types` can change a collection or tree an extension adds
 * (D-349).
 */
interface ContentTypeSource
{
	/**
	 * The container tag for content type sources.
	 */
	public const string TAG = 'content.types';

	/**
	 * Returns the types.
	 *
	 * @return iterable<ContentType>
	 */
	public function types(): iterable;
}
