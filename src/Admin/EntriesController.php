<?php

/**
 * Admin entries controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use DateTimeInterface;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\AccountStore;
use Blush\Auth\Accounts;
use Blush\Auth\AuthException;
use Blush\Auth\Capability;
use Blush\Auth\ContentAction;
use Blush\Auth\Permissions;
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\Entry\Position;
use Blush\Content\EntryFields;
use Blush\Content\Query\Order;
use Blush\Content\Query\Query;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Status;
use Blush\Content\Type\Collection;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Profiles;
use Blush\Content\Type\Tree;
use Blush\Core\AppConfig;
use Blush\Http\Response;
use Blush\Http\Status as HttpStatus;
use Blush\View\ThemedErrorPages;

/**
 * Answers `GET {path}/api/entries` (D-225, D-230): the entries the
 * account may edit, a page at a time, so an author sees their own and an
 * editor everyone's. The query string narrows the list:
 *
 * - `status`: `draft`, `scheduled`, `published`, `any` (the default,
 *   every status but `trash`), or `trash`: the entries in the trash the
 *   account may delete (D-484), most recently trashed first, each with
 *   its `trashed` date. The trash pins nothing and isn't a tree.
 * - `type`: a content type's name.
 * - `search`: text the title or file path must contain (any case).
 * - `author`: an author's slug the entries must credit (D-300).
 * - `terms`: `type:slug` pairs of term types (D-593), comma separated; an entry needs each.
 * - `days`: entries updated in the last so many days.
 * - `account`: for profiles, `linked` (an account is linked to them) or
 *   `guest` (none is; D-369).
 * - `sort`: `title`, `status`, `author`, `published`, or `updated`, and
 *   `dir`, `asc` or `desc` (the dates newest first unless `dir` says
 *   otherwise, the rest A to Z).
 * - `page` (from 1) and `per` (20 by default, at most 100).
 *
 * Unsorted, a type's whole list (any status) goes by `position`, then
 * title, for a tree or a positioned collection (D-412), newest published first for a
 * collection, and by title for profiles (D-413); drafts, and the list of every type, come most
 * recently changed first, scheduled entries soonest first, and
 * published entries newest first. `by` says which the list is in order
 * of (`position`, `published`, `updated`, or the column sorted by), so
 * the admin can show the date it goes by. The
 * permission rules, the filters, and paging all run in the index as one
 * query (`Permissions::restrict()`), so only the page's entries are built.
 *
 * A type whose entries nest (pages, and hierarchical collections; D-257, D-593)
 * lists as a tree when it's the whole list (no status, search, filter,
 * or sort): each
 * entry followed by its children, siblings by position, then title
 * (D-261, D-413), each with
 * its `depth` in the tree (0 at the top) for the admin to indent by, and
 * how many `children` it has (D-262). Its entries are all built to order
 * them, then paged; a page that starts inside a branch begins with the
 * entries above it, marked `continued` and not counted (D-263). An entry
 * whose parent isn't in the list sits at the top. Other lists' entries
 * have `depth` and `children` `null`, and none are `continued`.
 * Terms also say how many published entries use them (`uses`, D-236).
 *
 * A collection's **index page** (its landing page, in the
 * site's locale) isn't one of its entries (D-255): it's left out of the
 * entries, the total, and the pages, and answered on its own as `index`
 * on the first page (D-264), when it matches the filters and the account
 * may edit it; otherwise `null`. Pages pin their root page, `index.md`,
 * there the same way (D-420). Every entry says whether it's the
 * `homepage` and the `rootPage`, with `homeInstead` (what the homepage
 * shows) for a root page that isn't it, and `can.makeHomepage`. A type's **authors page**
 * (`_authors`, D-329; any relation archive's list page, D-602) is set apart the same way, as `archivePage`. The
 * site's **error pages** (`_errors/404.md`, D-411) are set apart from
 * Pages the same way, as `errorPages`, by status, each with its
 * `errorPage` status (else `null`), with anything else in the error
 * folders after them.
 */
final readonly class EntriesController
{
	/**
	 * How many entries a page holds unless `per` says otherwise.
	 */
	public const int PER_PAGE = 20;

	/**
	 * The most entries a page may hold.
	 */
	public const int MAX_PER_PAGE = 100;

	/**
	 * The columns a list sorts by.
	 *
	 * @var list<string>
	 */
	public const array SORTS = ['title', 'status', 'author', 'published', 'updated'];

	/**
	 * The most days `days` reaches back.
	 */
	private const int MAX_DAYS = 36500;

	public function __construct(
		private ContentRepository $content,
		private ContentTypes $types,
		private EntryHandles $handles,
		private ContentUrls $urls,
		private Permissions $permissions,
		private AppConfig $app,
		private ClockInterface $clock,
		private AccountStore $accounts,
		private Accounts $names,
		private Homepage $homepage,
		private ArchivePages $archivePages
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account) {
			return self::json(['error' => 'Sign in first.'], HttpStatus::Unauthorized);
		}

		$params = $request->getQueryParams();
		$value  = $params['status'] ?? 'any';
		$status = is_string($value) ? Status::tryFrom($value) : null;

		if ($status === null && $value !== 'any') {
			return self::json(['error' => '"status" must be draft, scheduled, published, trash, or any.'], HttpStatus::BadRequest);
		}

		$trash = $status === Status::Trash;

		$type = $params['type'] ?? null;

		if ($type !== null && (! is_string($type) || ! $this->types->has($type))) {
			return self::json(['error' => 'There is no such content type.'], HttpStatus::BadRequest);
		}

		$search = $params['search'] ?? '';

		if (! is_string($search)) {
			return self::json(['error' => '"search" must be text.'], HttpStatus::BadRequest);
		}

		$author = $params['author'] ?? '';

		if (! is_string($author)) {
			return self::json(['error' => '"author" must be an author\'s slug.'], HttpStatus::BadRequest);
		}

		$terms = self::terms($params['terms'] ?? '');

		if ($terms === null) {
			return self::json(['error' => '"terms" must be type:slug pairs, separated by commas.'], HttpStatus::BadRequest);
		}

		foreach ($terms as [$taxonomy]) {
			if ($this->types->classification($taxonomy) === null) {
				return self::json(['error' => sprintf('Nothing is filed under "%s"; it isn\'t a type of terms.', $taxonomy)], HttpStatus::BadRequest);
			}
		}

		$days = $params['days'] ?? '';
		$days = $days === '' ? 0 : self::positive($days);

		if ($days === null || $days > self::MAX_DAYS) {
			return self::json(['error' => sprintf('"days" must be a whole number from 1 to %d.', self::MAX_DAYS)], HttpStatus::BadRequest);
		}

		$link = $params['account'] ?? '';

		if (! in_array($link, ['', 'linked', 'guest'], true)) {
			return self::json(['error' => '"account" must be linked or guest.'], HttpStatus::BadRequest);
		}

		$sort = $params['sort'] ?? '';

		if ($sort !== '' && ! in_array($sort, self::SORTS, true)) {
			return self::json(['error' => '"sort" must be title, status, author, published, or updated.'], HttpStatus::BadRequest);
		}

		$order = match ($params['dir'] ?? '') {
			''      => $sort === 'updated' || $sort === 'published' ? Order::Desc : Order::Asc,
			'asc'   => Order::Asc,
			'desc'  => Order::Desc,
			default => null
		};

		if ($order === null) {
			return self::json(['error' => '"dir" must be asc or desc.'], HttpStatus::BadRequest);
		}

		$page = self::positive($params['page'] ?? '1');
		$per  = self::positive($params['per'] ?? (string) self::PER_PAGE);

		if ($page === null || $per === null || $per > self::MAX_PER_PAGE) {
			return self::json(['error' => sprintf('"page" must be a whole number from 1, and "per" from 1 to %d.', self::MAX_PER_PAGE)], HttpStatus::BadRequest);
		}

		$authors = $this->types->profiles()->name ?? '';
		$query   = $this->content->query()->any()->search($search);
		$query = $status === null ? $query : $query->status($status);
		$query = $type === null ? $query : $query->type($type);
		$query = $author === '' ? $query : $query->whereTerm($authors, $author);
		$query = $days === 0 ? $query : $query->updatedSince($this->clock->now()->getTimestamp() - $days * 86400);

		foreach ($terms as [$taxonomy, $slug]) {
			$query = $query->whereTerm($taxonomy, $slug);
		}

		// Unsorted, a type's All tab goes by position, then title, where
		// its entries have one (D-412), newest published first for a
		// collection, and by title for profiles (D-413).
		$contentType = $type === null ? null : $this->types->find($type);
		$positioned  = $contentType instanceof Tree || ($contentType instanceof Collection && $contentType->isPositioned());
		$by          = match (true) {
			$sort !== ''                                         => $sort,
			$trash                                               => EntryFields::TRASHED,
			$status === Status::Scheduled                        => 'published',
			$status === Status::Published                        => 'published',
			$status === null && $positioned                      => 'position',
			$status === null && $contentType instanceof Profiles => 'title',
			$status === null && $contentType !== null            => 'published',
			default                                              => 'updated'
		};

		$query = match (true) {
			$sort === 'author'            => $authors === '' ? $query : $query->orderBy($authors, $order),
			$sort !== ''                  => $query->orderBy($sort, $order),
			$status === Status::Scheduled => $query->orderBy('published', Order::Asc),
			$by === 'position'            => $query->orderBy('position', Order::Asc),
			$by === 'title'               => $query->orderBy('title', Order::Asc),
			default                       => $query->orderBy($by, Order::Desc)
		};

		$whole = $status === null && trim($search) === '' && $author === '' && $terms === [] && $days === 0 && $sort === '' && $link === '';

		$pinned      = $contentType !== null && ! $trash;
		$query       = $this->permissions->restrict($account, $trash ? ContentAction::Delete : ContentAction::Edit, $query);
		$listed      = $pinned ? $query->withLanding(false)->exceptNames(...$this->archivePages->listPages($contentType))->exceptIn(...$this->archivePages->targetFolders($contentType)) : $query;
		$listed      = $trash ? $listed->withLanding(false) : $listed;
		$linked      = $contentType instanceof Profiles ? $this->linked($account) : [];
		$listed      = match (true) {
			! $contentType instanceof Profiles || $link === '' => $listed,
			// A slug never has a "/", so with none linked, nothing is.
			$link === 'linked'                                 => $listed->names(...(array_map(strval(...), array_keys($linked)) ?: ['/'])),
			default                                            => $listed->exceptNames(...$this->archivePages->listPages($contentType), ...array_map(strval(...), array_keys($linked)))
		};
		$errors      = $pinned && $contentType instanceof Tree && $contentType->atRoot();
		$listed      = $errors ? $listed->exceptIn(...ThemedErrorPages::FOLDERS) : $listed;
		$errorPages  = $errors && $page === 1 ? $this->errorPages($query) : [];
		$index       = $pinned && $page === 1 ? $this->index($query) : null;
		$archive     = $pinned && $page === 1 ? $this->archivePage($query, $contentType) : null;
		$counts      = [];
		$tree        = null;
		$continued   = [];

		if ($contentType !== null && $whole && $this->nests($contentType)) {
			$tree      = self::tree($listed->limit(null)->get()->all());
			$start     = ($page - 1) * $per;
			$total     = count($tree['entries']);
			$pages     = (int) ceil($total / $per);
			$shown     = array_slice($tree['entries'], $start, $per);
			$continued = self::continued($tree, $start);
		} else {
			$entries = $listed->paginate($per, $page);
			$total   = $entries->total();
			$pages   = $entries->pages();
			$shown   = $entries->all();
		}

		// How many published entries use each term on the page, one pass
		// per term type (D-236).
		foreach ([...$continued, ...$shown, ...($index === null ? [] : [$index]), ...($archive === null ? [] : [$archive]), ...$errorPages] as $entry) {
			if ($this->types->isTermType($entry->type->name)) {
				$counts[$entry->type->name] ??= $this->content->termCounts($entry->type->name);
			}
		}

		return self::json([
			'status'      => $status->value ?? 'any',
			'type'        => $type,
			'search'      => trim($search),
			'author'      => $author,
			'terms'       => array_map(static fn (array $term): string => implode(':', $term), $terms),
			'days'        => $days === 0 ? null : $days,
			'account'     => $link === '' ? null : $link,
			'sort'        => $sort === '' ? null : $sort,
			'dir'         => $sort === '' ? null : $order->value,
			'tree'        => $tree !== null,
			'by'          => $tree !== null ? 'position' : $by,
			'total'       => $total,
			'page'        => $page,
			'pages'       => $pages,
			'per'         => $per,
			'entries'     => [
				...array_map(fn (Entry $entry): array => $this->describe($account, $entry, $counts, $tree, continued: true, linked: $linked), $continued),
				...array_map(fn (Entry $entry): array => $this->describe($account, $entry, $counts, $tree, linked: $linked), $shown)
			],
			'index'       => $index === null ? null : $this->describe($account, $index, $counts),
			'archivePage' => $archive === null ? null : $this->describe($account, $archive, $counts),
			'errorPages'  => array_map(fn (Entry $entry): array => $this->describe($account, $entry, $counts), $errorPages)
		]);
	}

	/**
	 * Reads `terms`: `taxonomy:slug` pairs, comma separated, or `null`
	 * when it isn't that.
	 *
	 * @return ?list<array{string, string}>
	 */
	private static function terms(mixed $value): ?array
	{
		if (! is_string($value)) {
			return null;
		}

		$terms = [];

		foreach (array_filter(array_map(trim(...), explode(',', $value)), static fn (string $pair): bool => $pair !== '') as $pair) {
			$parts = explode(':', $pair, 2);

			if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
				return null;
			}

			$terms[] = [$parts[0], $parts[1]];
		}

		return $terms;
	}

	/**
	 * Returns whether a type's entries nest (D-257).
	 */
	private function nests(ContentType $type): bool
	{
		return $type instanceof Tree || $this->types->nestsByParent($type->name);
	}

	/**
	 * Returns entries in tree order, each followed by its children and
	 * siblings by position, then title (D-412, D-413), with each one's depth and how many children it
	 * has, by ID. An entry whose parent isn't among them is at the top;
	 * entries in a loop of parents come last, by title, at depth 0.
	 *
	 * @param  list<Entry> $entries
	 * @return array{entries: list<Entry>, depths: array<string, int>, children: array<string, int>}
	 */
	private static function tree(array $entries): array
	{
		// Siblings by position, then title (D-412, D-413).
		$byTitle = static fn (Entry $a, Entry $b): int => Position::siblings($a, $b) ?: strcmp($a->key, $b->key);
		$keys    = array_flip(array_map(static fn (Entry $entry): string => $entry->key, $entries));
		$roots   = [];
		$under   = [];

		foreach ($entries as $entry) {
			$parent = $entry->type->parentKey($entry->key, $entry->fields);

			if ($parent !== null && isset($keys[$parent])) {
				$under[$parent][] = $entry;
			} else {
				$roots[] = $entry;
			}
		}

		usort($roots, $byTitle);

		$ordered = [];
		$depths  = [];
		$stack   = array_map(static fn (Entry $entry): array => [$entry, 0], array_reverse($roots));

		while (($item = array_pop($stack)) !== null) {
			[$entry, $depth] = $item;

			if (isset($depths[$entry->path])) {
				continue;
			}

			$depths[$entry->path] = $depth;
			$ordered[]          = $entry;
			$children           = $under[$entry->key] ?? [];

			usort($children, $byTitle);
			array_push($stack, ...array_map(static fn (Entry $child): array => [$child, $depth + 1], array_reverse($children)));
		}

		$rest = array_values(array_filter($entries, static fn (Entry $entry): bool => ! isset($depths[$entry->path])));
		usort($rest, $byTitle);

		$children = [];

		foreach ($entries as $entry) {
			$children[$entry->path] = count($under[$entry->key] ?? []);
		}

		return ['entries' => [...$ordered, ...$rest], 'depths' => $depths, 'children' => $children];
	}

	/**
	 * Returns the entries above the first one on a page of a tree, from
	 * the top down, so a page that starts inside a branch shows where it
	 * is (D-263). They're on an earlier page already, so they aren't
	 * counted again.
	 *
	 * @param  array{entries: list<Entry>, depths: array<string, int>, children: array<string, int>} $tree
	 * @return list<Entry>
	 */
	private static function continued(array $tree, int $start): array
	{
		$first = $tree['entries'][$start] ?? null;
		$want  = $first === null ? -1 : ($tree['depths'][$first->path] ?? 0) - 1;
		$above = [];

		for ($i = $start - 1; $i >= 0 && $want >= 0; $i--) {
			$entry = $tree['entries'][$i];

			if (($tree['depths'][$entry->path] ?? 0) === $want) {
				array_unshift($above, $entry);
				$want--;
			}
		}

		return $above;
	}

	/**
	 * Returns the type's index page, or Pages' root page, if the list's
	 * query finds it.
	 */
	private function index(Query $query): ?Entry
	{
		foreach ($query->names('index')->get() as $entry) {
			if ((IndexPage::is($entry) || Homepage::isRootPage($entry)) && $entry->language === $this->app->languages->default->code) {
				return $entry;
			}
		}

		return null;
	}

	/**
	 * Returns what the list's query finds in the error folders (D-411):
	 * the error pages by status, then anything else kept there, by title.
	 *
	 * @return list<Entry>
	 */
	private function errorPages(Query $query): array
	{
		$pages = [];

		foreach (ThemedErrorPages::FOLDERS as $folder) {
			foreach ($query->in($folder)->limit(null)->get() as $entry) {
				if ($entry->language === $this->app->languages->default->code) {
					$pages[] = $entry;
				}
			}
		}

		usort($pages, static fn (Entry $a, Entry $b): int => [ErrorPage::status($a) ?? 1000, $a->title, $a->id] <=> [ErrorPage::status($b) ?? 1000, $b->title, $b->id]);

		return $pages;
	}

	/**
	 * Returns the type's first relation archive list page (D-602), if the
	 * list's query finds it.
	 */
	private function archivePage(Query $query, ContentType $type): ?Entry
	{
		$names = $this->archivePages->listPages($type);

		foreach ($names === [] ? [] : $query->names(...$names)->exceptIn(...$this->archivePages->targetFolders($type))->get() as $entry) {
			if ($this->archivePages->isList($entry) && $entry->language === $this->app->languages->default->code) {
				return $entry;
			}
		}

		return null;
	}

	/**
	 * Returns what the admin shows of an entry. Its `url` is its path on
	 * the site, where it is or will be once published (`null` when it has
	 * none), and `index` whether it's its type's index page. A term's
	 * `uses` is how many published entries reference it; other entries'
	 * is `null`. `ancestors` are the titles of the entries above it, from
	 * the top down: a page's folders' pages, or a hierarchical term's
	 * parents. In a tree-ordered list, `depth` (0 at the top) and
	 * `children` (how many) place it, and `continued` marks an entry that
	 * heads a later page for the entries under it; otherwise the first
	 * two are `null` (D-262, D-263). A profile (D-353) says whether an
	 * account is `linked` to it and, for whoever sees accounts, which
	 * (`account`: `{"username", "displayName"}`, else `null`), and whether
	 * it can be linked (`linkable`, `false` when locked, D-605).
	 *
	 * @param  array<string, array<string, int>>                                                     $counts Term counts by taxonomy.
	 * @param  ?array{entries: list<Entry>, depths: array<string, int>, children: array<string, int>} $tree   The list's tree, if it's one.
	 * @param  array<string, ?array{username: string, displayName: string}>                          $linked Accounts by the profile they link to (`null` when the viewer can't see which).
	 * @return array<string, mixed>
	 */
	private function describe(Account $account, Entry $entry, array $counts, ?array $tree = null, bool $continued = false, array $linked = []): array
	{
		$authors = $this->types->profiles()?->name;
		$home    = $this->homepage->describe($entry);

		return [
			'path'        => $entry->path,
			'id'          => $entry->id,
			'handle'      => $this->handles->of($entry),
			'title'       => $entry->title,
			'type'        => $entry->type->name,
			'status'      => $entry->status->value,
			'trashed'     => EntryController::trashed($entry),
			'published'   => $entry->published?->format(DateTimeInterface::ATOM),
			'updated'     => $entry->updated->format(DateTimeInterface::ATOM),
			'url'         => $this->urls->entry($entry),
			'authors'     => $authors === null ? [] : $entry->terms($authors),
			'own'         => $this->permissions->owns($account, $entry),
			'index'       => IndexPage::is($entry),
			'archivePage' => $this->archivePages->isList($entry),
			'errorPage'   => ErrorPage::status($entry),
			'archiveLabel' => ($relation = $this->archivePages->relationOf($entry)) === null ? null : ($relation->label === '' ? ucfirst(str_replace('_', ' ', $relation->name)) : $relation->label),
			...$home,
			'can'         => [
				'delete'       => ! IndexPage::is($entry) && $this->permissions->can($account, ContentAction::Delete, $entry),
				'duplicate'    => ! $entry->landing && ! $this->archivePages->isList($entry) && ErrorPage::status($entry) === null && $this->permissions->can($account, ContentAction::Create, $entry->type->name),
				'makeHomepage' => $home['homeInstead'] !== null && $this->permissions->can($account, Capability::SiteSettings->value)
			],
			'uses'        => $this->types->isTermType($entry->type->name) ? ($counts[$entry->type->name][$entry->key] ?? 0) : null,
			'ancestors'   => $this->ancestors($entry),
			'depth'       => $tree === null ? null : ($tree['depths'][$entry->path] ?? 0),
			'children'    => $tree === null ? null : ($tree['children'][$entry->path] ?? 0),
			'continued'   => $continued,
			...($entry->type instanceof Profiles ? ['linked' => array_key_exists($entry->key, $linked), 'account' => $linked[$entry->key] ?? null, 'linkable' => $entry->field('linkable') !== false] : [])
		];
	}

	/**
	 * Returns the accounts linked to profiles, by profile slug: each one's
	 * `username` and `displayName` when the viewer manages accounts, else
	 * `null`, so a list can still tell a guest profile from a linked one.
	 *
	 * @return array<string, ?array{username: string, displayName: string}>
	 */
	private function linked(Account $viewer): array
	{
		try {
			$accounts = $this->accounts->all();
		} catch (AuthException) {
			return [];
		}

		$manages = $this->permissions->can($viewer, Capability::AccountsView);
		$linked  = [];

		foreach ($accounts as $account) {
			if ($account->author !== null && ! array_key_exists($account->author, $linked)) {
				$linked[$account->author] = $manages ? ['username' => $account->username, 'displayName' => $this->names->displayName($account)] : null;
			}
		}

		return $linked;
	}

	/**
	 * Returns the titles of an entry's ancestors, from the top down,
	 * whatever their status. The chain stops at a missing parent or a
	 * loop.
	 *
	 * @return list<string>
	 */
	private function ancestors(Entry $entry): array
	{
		$titles = [];
		$seen   = [$entry->path => true];

		while (($entry = $this->content->parent($entry)) !== null && ! isset($seen[$entry->path])) {
			$seen[$entry->path] = true;
			array_unshift($titles, $entry->title === '' ? $entry->slug : $entry->title);
		}

		return $titles;
	}

	/**
	 * Reads a whole number from 1 up, or `null` when it isn't one.
	 */
	private static function positive(mixed $value): ?int
	{
		return is_string($value) && preg_match('/^[1-9][0-9]{0,8}$/', $value) === 1 ? (int) $value : null;
	}

	/**
	 * Returns a JSON answer the browser won't cache.
	 *
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, HttpStatus $status = HttpStatus::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}
}
