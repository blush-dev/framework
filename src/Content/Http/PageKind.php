<?php

/**
 * Content page kind.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Http;

/**
 * What a content page shows, which decides its template hierarchy in M5.
 */
enum PageKind: string
{
	/**
	 * The home page: the home type's collection, or `index.md`.
	 */
	case Home = 'home';

	/**
	 * One entry of a routed type.
	 */
	case Single = 'single';

	/**
	 * One entry served by the page catch-all.
	 */
	case Page = 'page';

	/**
	 * A type's collection.
	 */
	case Collection = 'collection';

	/**
	 * A taxonomy term's archive.
	 */
	case Term = 'term';

	/**
	 * A date archive.
	 */
	case Date = 'date';
}
