<?php

/**
 * Error page.
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
use Blush\View\ThemedErrorPages;

/**
 * An **error page** (D-108, D-411): a page in `_errors/` (or 1.x's
 * `_error/`) named for an HTTP status, `_errors/404.md`, whose title and
 * body the site shows for that error. The admin pins error pages at the
 * top of Pages, marked with their status, and leaves them out of the
 * tree and of what a page can go under; they don't move or take a new
 * slug, since their name is their status.
 */
final class ErrorPage
{
	/**
	 * Returns the status an entry is the error page for, or `null` when
	 * it isn't one.
	 */
	public static function status(Entry $entry): ?int
	{
		if (! $entry->type instanceof Tree || ! $entry->type->atRoot() || ! in_array(dirname($entry->key), ThemedErrorPages::FOLDERS, true)) {
			return null;
		}

		return preg_match('/^[45]\d\d$/', $entry->slug) === 1 ? (int) $entry->slug : null;
	}
}
