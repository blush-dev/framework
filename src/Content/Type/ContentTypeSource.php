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
 * source with `ContentTypeSource::TAG` in a provider's `TAGS`. The site's
 * `config/content.php` can redefine any type an extension adds.
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
