<?php

/**
 * Content page.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Http;

use Closure;
use Blush\Content\Entry\Entry;
use Blush\Content\Query\PageLink;
use Blush\Content\Query\Paginator;
use Blush\Content\Relation\Relation;
use Blush\Content\Type\ContentType;
use Blush\Setup\Welcome;

/**
 * What a content controller found, handed to the `PageRenderer`: the kind
 * of page, its title, the entry it shows (a single, a landing page, or a
 * term), and the entries it lists.
 *
 * `$pageUrl` returns the URL of another page of the listing, for
 * pagination links. For the homepage, `$base` is the kind of page it
 * shows: the home type's `Collection`, or `index.md` as a `Page`.
 *
 * A relation archive's pages (D-596, D-602) say which relation they're
 * for, and a target's archive which entry it's about (`$target`): its
 * `$entry` is the page written for that archive when there is one, else
 * the target itself. A profile's own page is about its profile.
 *
 * The welcome page carries its `Welcome` notes.
 *
 * `$alternateUrl` returns the page's URL path in another language (by
 * code), or `null` when it has none there (D-461): a single's published
 * translation, or the same page of a listing in a language that lists
 * entries for it. The renderer turns them into `hreflang` alternates.
 */
final readonly class ContentPage
{
	/**
	 * @param ?Entry                     $entry   The entry shown: a single, a landing page, or a term.
	 * @param ?Paginator                 $entries The entries listed, if any.
	 * @param array<string, int>         $date    A date archive's date parts, from the year down.
	 * @param ?Closure(int): ?string     $pageUrl Returns another page's URL path.
	 * @param ?PageKind                  $base    For the homepage, the kind of page it shows.
	 * @param ?Relation                  $relation The relation a related list or a target's archive is for (D-596).
	 * @param ?Entry                     $target  The entry a target's archive or a profile's page is about.
	 * @param ?Welcome                   $welcome The welcome page's notes.
	 * @param ?string                    $language The code of the language the page is in (D-455), when it's a list in another language; `null` for its entry's or the default.
	 * @param ?Closure(string): ?string  $alternateUrl Returns the page's URL path in a language, or `null`.
	 */
	public function __construct(
		public PageKind $kind,
		public string $title,
		public ?Entry $entry = null,
		public ?ContentType $type = null,
		public ?Paginator $entries = null,
		public array $date = [],
		public ?Closure $pageUrl = null,
		public ?PageKind $base = null,
		public ?Welcome $welcome = null,
		public ?string $language = null,
		public ?Closure $alternateUrl = null,
		public ?Relation $relation = null,
		public ?Entry $target = null
	) {}

	/**
	 * Returns the page's URL path in a language (by code), or `null`
	 * when it has none there.
	 */
	public function alternateUrl(string $language): ?string
	{
		return $this->alternateUrl === null ? null : ($this->alternateUrl)($language);
	}

	/**
	 * Returns the URL path of a page of the listing.
	 */
	public function pageUrl(int $page): ?string
	{
		return $this->pageUrl === null ? null : ($this->pageUrl)($page);
	}

	/**
	 * Returns the listing's numbered pagination, with each page's URL
	 * (see `Paginator::links()`). A page that lists nothing, or lists
	 * one page, has none.
	 *
	 * @return list<PageLink>
	 */
	public function pageLinks(int $endSize = 1, int $midSize = 1, bool $adjacent = true): array
	{
		return $this->entries?->links($this->pageUrl(...), $endSize, $midSize, $adjacent) ?? [];
	}
}
