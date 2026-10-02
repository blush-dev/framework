<?php

/**
 * Index pages.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Blush\Content\Entry\Entry;
use Blush\Content\Type\Tree;

/**
 * A collection's or taxonomy's **index page** (D-255): its landing page,
 * the `index` file in its folder. The admin pins it in its type's list,
 * edits it without the type's fields or scheduling, and never trashes it
 * (D-274). Pages have none: the root tree's root is the site, so the home
 * page is a page like the others. A tree in a folder has one (D-386).
 */
final class IndexPage
{
	/**
	 * Whether an entry is its type's index page.
	 */
	public static function is(Entry $entry): bool
	{
		return $entry->landing && ! ($entry->type instanceof Tree && $entry->type->atRoot());
	}
}
