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
use Blush\Content\Type\ContentType;
use Blush\Content\Type\PeopleField;
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
 * The people pages (D-351) say which people field they're for, and a
 * person's archive which profile: its `$entry` is the page written for
 * that archive when there is one, else the profile itself.
 *
 * The welcome page carries its `Welcome` notes.
 */
final readonly class ContentPage
{
	/**
	 * @param ?Entry                     $entry   The entry shown: a single, a landing page, or a term.
	 * @param ?Paginator                 $entries The entries listed, if any.
	 * @param array<string, int>         $date    A date archive's date parts, from the year down.
	 * @param ?Closure(int): ?string     $pageUrl Returns another page's URL path.
	 * @param ?PageKind                  $base    For the homepage, the kind of page it shows.
	 * @param ?PeopleField               $people  The people field a people list or person's archive is for.
	 * @param ?Entry                     $profile The profile a person's archive or profile page is about.
	 * @param ?Welcome                   $welcome The welcome page's notes.
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
		public ?PeopleField $people = null,
		public ?Entry $profile = null,
		public ?Welcome $welcome = null
	) {}

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
