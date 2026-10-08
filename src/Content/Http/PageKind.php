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
 * What a content page shows, which decides its template hierarchy (`View\Hierarchy`).
 */
enum PageKind: string
{
	/**
	 * The homepage: the home type's collection, or `index.md`.
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

	/**
	 * What a type's relation links to, with an archive word (D-596):
	 * `/movies/actors`.
	 */
	case RelatedList = 'related-list';

	/**
	 * A target's archive under a type's relation: `/movies/actors/tom`.
	 */
	case Related = 'related';

	/**
	 * A profile's own page: the bio, and everything crediting them.
	 */
	case Profile = 'profile';

	/**
	 * The welcome page of a site with no homepage yet.
	 */
	case Welcome = 'welcome';
}
