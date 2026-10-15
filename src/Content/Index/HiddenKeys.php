<?php

/**
 * Hidden keys.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Index;

use Blush\Content\Http\RelatedController;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Tree;
use Blush\View\ThemedErrorPages;

/**
 * The places core keeps pages that are served somewhere else, never by
 * themselves (D-855), so their entries are hidden whatever their front
 * matter says: a relation archive's own pages in a type's folder
 * (`_authors`, `_authors/jane`, D-602), and the site's error pages
 * (`_errors/404`, D-411) with the folder's own page. A `_` name means
 * nothing else: front matter says an entry's visibility.
 */
final class HiddenKeys
{
	/**
	 * Returns whether an entry of a type at a key is in one of them.
	 */
	public static function has(ContentTypes $types, ContentType $type, string $key): bool
	{
		$first = explode('/', $key)[0];

		if ($type instanceof Tree && $type->atRoot()) {
			return in_array($first, ThemedErrorPages::FOLDERS, true);
		}

		return in_array($first, array_map(RelatedController::word(...), $types->relationArchives($type)), true);
	}
}
