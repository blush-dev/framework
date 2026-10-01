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
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\Query\Order;
use Blush\Content\Query\Query;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Status;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Pages;
use Blush\Content\Http\AuthorsController;
use Blush\Content\Type\Taxonomy;
use Blush\Content\Type\TypeKind;
use Blush\Core\AppConfig;
use Blush\Http\Response;
use Blush\Http\Status as HttpStatus;

/**
 * Answers `GET {path}/api/entries` (D-225, D-230): the entries the
 * account may edit, a page at a time, so an author sees their own and an
 * editor everyone's. The query string narrows the list:
 *
 * - `status`: `draft`, `scheduled`, `published`, or `any` (the default).
 * - `type`: a content type's name.
 * - `search`: text the title or file path must contain (any case).
 * - `author`: an author's slug the entries must credit (D-300).
 * - `terms`: `taxonomy:slug` pairs, comma separated; an entry needs each.
 * - `days`: entries updated in the last so many days.
 * - `sort`: `title`, `status`, `author`, or `updated`, and `dir`, `asc`
 *   or `desc` (`updated` newest first unless `dir` says otherwise, the
 *   rest A to Z).
 * - `page` (from 1) and `per` (20 by default, at most 100).
 *
 * Unsorted, drafts and the whole list come most recently changed first, scheduled
 * entries soonest first, and published entries newest first. The
 * permission rules, the filters, and paging all run in the index as one
 * query (`Permissions::restrict()`), so only the page's entries are built.
 *
 * A type whose entries nest (pages, and hierarchical taxonomies; D-257)
 * lists as a tree when it's the whole list (no status, search, filter,
 * or sort): each
 * entry followed by its children, siblings by title (D-261), each with
 * its `depth` in the tree (0 at the top) for the admin to indent by, and
 * how many `children` it has (D-262). Its entries are all built to order
 * them, then paged; a page that starts inside a branch begins with the
 * entries above it, marked `continued` and not counted (D-263). An entry
 * whose parent isn't in the list sits at the top. Other lists' entries
 * have `depth` and `children` `null`, and none are `continued`.
 * Terms also say how many published entries use them (`uses`, D-236).
 *
 * A collection's or taxonomy's **index page** (its landing page, in the
 * site's locale) isn't one of its entries (D-255): it's left out of the
 * entries, the total, and the pages, and answered on its own as `index`
 * on the first page (D-264), when it matches the filters and the account
 * may edit it; otherwise `null`. Pages have no index page: their landing page is
 * the site's home, an entry like any other. A type's **authors page**
 * (`_authors`, D-329) is set apart the same way, as `authorsPage`.
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
	public const array SORTS = ['title', 'status', 'author', 'updated'];

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
		private ClockInterface $clock
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
			return self::json(['error' => '"status" must be draft, scheduled, published, or any.'], HttpStatus::BadRequest);
		}

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
			return self::json(['error' => '"terms" must be taxonomy:slug pairs, separated by commas.'], HttpStatus::BadRequest);
		}

		foreach ($terms as [$taxonomy]) {
			if (! $this->types->find($taxonomy) instanceof Taxonomy) {
				return self::json(['error' => sprintf('There is no taxonomy "%s".', $taxonomy)], HttpStatus::BadRequest);
			}
		}

		$days = $params['days'] ?? '';
		$days = $days === '' ? 0 : self::positive($days);

		if ($days === null || $days > self::MAX_DAYS) {
			return self::json(['error' => sprintf('"days" must be a whole number from 1 to %d.', self::MAX_DAYS)], HttpStatus::BadRequest);
		}

		$sort = $params['sort'] ?? '';

		if ($sort !== '' && ! in_array($sort, self::SORTS, true)) {
			return self::json(['error' => '"sort" must be title, status, author, or updated.'], HttpStatus::BadRequest);
		}

		$order = match ($params['dir'] ?? '') {
			''      => $sort === 'updated' ? Order::Desc : Order::Asc,
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

		$authors = $this->types->authors()->name ?? '';
		$query   = $this->content->query()->any()->search($search);
		$query = $status === null ? $query : $query->status($status);
		$query = $type === null ? $query : $query->type($type);
		$query = $author === '' ? $query : $query->whereTerm($authors, $author);
		$query = $days === 0 ? $query : $query->updatedSince($this->clock->now()->getTimestamp() - $days * 86400);

		foreach ($terms as [$taxonomy, $slug]) {
			$query = $query->whereTerm($taxonomy, $slug);
		}

		$query = match (true) {
			$sort === 'author'            => $authors === '' ? $query : $query->orderBy($authors, $order),
			$sort !== ''                  => $query->orderBy($sort, $order),
			$status === Status::Scheduled => $query->orderBy('published', Order::Asc),
			$status === Status::Published => $query->orderBy('published', Order::Desc),
			default                       => $query->orderBy('updated', Order::Desc)
		};

		$whole = $status === null && trim($search) === '' && $author === '' && $terms === [] && $days === 0 && $sort === '';

		$contentType = $type === null ? null : $this->types->find($type);
		$pinned      = $contentType !== null && $contentType->kind() !== TypeKind::Pages;
		$query       = $this->permissions->restrict($account, Capability::ContentEdit, $query);
		$listed      = $pinned ? $query->withLanding(false)->exceptNames(AuthorsController::PAGE) : $query;
		$index       = $pinned && $page === 1 ? $this->index($query) : null;
		$people      = $pinned && $page === 1 ? $this->authorsPage($query) : null;
		$counts      = [];
		$tree        = null;
		$continued   = [];

		if ($contentType !== null && $whole && self::nests($contentType)) {
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
		// per taxonomy (D-236).
		foreach ([...$continued, ...$shown, ...($index === null ? [] : [$index]), ...($people === null ? [] : [$people])] as $entry) {
			if ($entry->type->hasTerms()) {
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
			'sort'        => $sort === '' ? null : $sort,
			'dir'         => $sort === '' ? null : $order->value,
			'tree'        => $tree !== null,
			'total'       => $total,
			'page'        => $page,
			'pages'       => $pages,
			'per'         => $per,
			'entries'     => [
				...array_map(fn (Entry $entry): array => $this->describe($account, $entry, $counts, $tree, continued: true), $continued),
				...array_map(fn (Entry $entry): array => $this->describe($account, $entry, $counts, $tree), $shown)
			],
			'index'       => $index === null ? null : $this->describe($account, $index, $counts),
			'authorsPage' => $people === null ? null : $this->describe($account, $people, $counts)
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
	private static function nests(ContentType $type): bool
	{
		return $type instanceof Pages || ($type instanceof Taxonomy && $type->hierarchical);
	}

	/**
	 * Returns entries in tree order, each followed by its children and
	 * siblings by title, with each one's depth and how many children it
	 * has, by ID. An entry whose parent isn't among them is at the top;
	 * entries in a loop of parents come last, by title, at depth 0.
	 *
	 * @param  list<Entry> $entries
	 * @return array{entries: list<Entry>, depths: array<string, int>, children: array<string, int>}
	 */
	private static function tree(array $entries): array
	{
		$byTitle = static fn (Entry $a, Entry $b): int => strnatcasecmp($a->title, $b->title) ?: strcmp($a->key, $b->key);
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

			if (isset($depths[$entry->id])) {
				continue;
			}

			$depths[$entry->id] = $depth;
			$ordered[]          = $entry;
			$children           = $under[$entry->key] ?? [];

			usort($children, $byTitle);
			array_push($stack, ...array_map(static fn (Entry $child): array => [$child, $depth + 1], array_reverse($children)));
		}

		$rest = array_values(array_filter($entries, static fn (Entry $entry): bool => ! isset($depths[$entry->id])));
		usort($rest, $byTitle);

		$children = [];

		foreach ($entries as $entry) {
			$children[$entry->id] = count($under[$entry->key] ?? []);
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
		$want  = $first === null ? -1 : ($tree['depths'][$first->id] ?? 0) - 1;
		$above = [];

		for ($i = $start - 1; $i >= 0 && $want >= 0; $i--) {
			$entry = $tree['entries'][$i];

			if (($tree['depths'][$entry->id] ?? 0) === $want) {
				array_unshift($above, $entry);
				$want--;
			}
		}

		return $above;
	}

	/**
	 * Returns the type's index page, if the list's query finds it.
	 */
	private function index(Query $query): ?Entry
	{
		foreach ($query->names('index')->get() as $entry) {
			if (IndexPage::is($entry) && $entry->locale === $this->app->locale) {
				return $entry;
			}
		}

		return null;
	}

	/**
	 * Returns the type's authors page (D-329), if the list's query finds
	 * it.
	 */
	private function authorsPage(Query $query): ?Entry
	{
		foreach ($query->names(AuthorsController::PAGE)->get() as $entry) {
			if (AuthorsPage::is($entry) && $entry->locale === $this->app->locale) {
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
	 * two are `null` (D-262, D-263).
	 *
	 * @param  array<string, array<string, int>>                                                     $counts Term counts by taxonomy.
	 * @param  ?array{entries: list<Entry>, depths: array<string, int>, children: array<string, int>} $tree   The list's tree, if it's one.
	 * @return array<string, mixed>
	 */
	private function describe(Account $account, Entry $entry, array $counts, ?array $tree = null, bool $continued = false): array
	{
		$authors = $this->types->authors()?->name;

		return [
			'id'          => $entry->id,
			'handle'      => $this->handles->of($entry),
			'title'       => $entry->title,
			'type'        => $entry->type->name,
			'status'      => $entry->status->value,
			'published'   => $entry->published?->format(DateTimeInterface::ATOM),
			'updated'     => $entry->updated->format(DateTimeInterface::ATOM),
			'path'        => $entry->source?->path,
			'url'         => $this->urls->entry($entry),
			'authors'     => $authors === null ? [] : $entry->terms($authors),
			'own'         => $this->permissions->owns($account, $entry),
			'index'       => IndexPage::is($entry),
			'authorsPage' => AuthorsPage::is($entry),
			'can'         => [
				'delete'    => ! IndexPage::is($entry) && $this->permissions->can($account, Capability::ContentDelete, $entry),
				'duplicate' => ! $entry->landing && ! AuthorsPage::is($entry) && $this->permissions->can($account, Capability::ContentCreate)
			],
			'uses'        => $entry->type->hasTerms() ? ($counts[$entry->type->name][$entry->key] ?? 0) : null,
			'ancestors'   => $this->ancestors($entry),
			'depth'       => $tree === null ? null : ($tree['depths'][$entry->id] ?? 0),
			'children'    => $tree === null ? null : ($tree['children'][$entry->id] ?? 0),
			'continued'   => $continued
		];
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
		$seen   = [$entry->id => true];

		while (($entry = $this->content->parent($entry)) !== null && ! isset($seen[$entry->id])) {
			$seen[$entry->id] = true;
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
