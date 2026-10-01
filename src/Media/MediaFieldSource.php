<?php

/**
 * Media field source.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

/**
 * Supplies media metadata fields from an extension (D-287), as
 * `ContentTypeSource` supplies content types. An extension tags its
 * source with `MediaFieldSource::TAG` in a provider's `TAGS`. A site's
 * data file and `config/media.php` can redefine any field an extension
 * adds.
 */
interface MediaFieldSource
{
	/**
	 * The container tag for media field sources.
	 */
	public const string TAG = 'media.fields';

	/**
	 * Returns the field sets.
	 *
	 * @return iterable<MediaFieldSet>
	 */
	public function fieldSets(): iterable;
}
