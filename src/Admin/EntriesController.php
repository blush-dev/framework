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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\AuthConfig;
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
 * - `page` (from 1) and `per` (20 by default, at most 100).
 *
 * Drafts and the whole list come most recently changed first, scheduled
 * entries soonest first, and published entries newest first. The
 * permission rules, the filters, and paging all run in the index as one
 * query (`Permissions::restrict()`), so only the page's entries are built.
 *
 * A type whose entries nest (pages, and hierarchical taxonomies; D-257)
 * lists as a tree when it's the whole list (no status, no search): each
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
 * the site's home, an entry like any other.
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

	public function __construct(
		private ContentRepository $content,
		private ContentTypes $types,
		private EntryHandles $handles,
		private ContentUrls $urls,
		private Permissions $permissions,
		private AuthConfig $config,
		private AppConfig $app
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

		$page = self::positive($params['page'] ?? '1');
		$per  = self::positive($params['per'] ?? (string) self::PER_PAGE);

		if ($page === null || $per === null || $per > self::MAX_PER_PAGE) {
			return self::json(['error' => sprintf('"page" must be a whole number from 1, and "per" from 1 to %d.', self::MAX_PER_PAGE)], HttpStatus::BadRequest);
		}

		$query = $this->content->query()->any()->search($search);
		$query = $status === null ? $query : $query->status($status);
		$query = $type === null ? $query : $query->type($type);
		$query = match ($status) {
			Status::Scheduled => $query->orderBy('published', Order::Asc),
			Status::Published => $query->orderBy('published', Order::Desc),
			default           => $query->orderBy('updated', Order::Desc)
		};

		$contentType = $type === null ? null : $this->types->find($type);
		$pinned      = $contentType !== null && $contentType->kind() !== TypeKind::Pages;
		$query       = $this->permissions->restrict($account, Capability::ContentEdit, $query);
		$listed      = $pinned ? $query->withLanding(false) : $query;
		$index       = $pinned && $page === 1 ? $this->index($query) : null;
		$counts      = [];
		$tree        = null;
		$continued   = [];

		if ($contentType !== null && $status === null && trim($search) === '' && self::nests($contentType)) {
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
		foreach ([...$continued, ...$shown, ...($index === null ? [] : [$index])] as $entry) {
			if ($entry->type instanceof Taxonomy) {
				$counts[$entry->type->name] ??= $this->content->termCounts($entry->type->name);
			}
		}

		return self::json([
			'status'  => $status->value ?? 'any',
			'type'    => $type,
			'search'  => trim($search),
			'total'   => $total,
			'page'    => $page,
			'pages'   => $pages,
			'per'     => $per,
			'entries' => [
				...array_map(fn (Entry $entry): array => $this->describe($account, $entry, $counts, $tree, continued: true), $continued),
				...array_map(fn (Entry $entry): array => $this->describe($account, $entry, $counts, $tree), $shown)
			],
			'index'   => $index === null ? null : $this->describe($account, $index, $counts)
		]);
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
		return [
			'id'        => $entry->id,
			'handle'    => $this->handles->of($entry),
			'title'     => $entry->title,
			'type'      => $entry->type->name,
			'status'    => $entry->status->value,
			'published' => $entry->published?->format(DateTimeInterface::ATOM),
			'updated'   => $entry->updated->format(DateTimeInterface::ATOM),
			'path'      => $entry->source?->path,
			'url'       => $this->urls->entry($entry),
			'authors'   => $entry->terms($this->config->authorTaxonomy),
			'own'       => $this->permissions->owns($account, $entry),
			'index'     => IndexPage::is($entry),
			'can'       => [
				'delete'    => ! IndexPage::is($entry) && $this->permissions->can($account, Capability::ContentDelete, $entry),
				'duplicate' => ! $entry->landing && $this->permissions->can($account, Capability::ContentCreate)
			],
			'uses'      => $entry->type instanceof Taxonomy ? ($counts[$entry->type->name][$entry->key] ?? 0) : null,
			'ancestors' => $this->ancestors($entry),
			'depth'     => $tree === null ? null : ($tree['depths'][$entry->id] ?? 0),
			'children'  => $tree === null ? null : ($tree['children'][$entry->id] ?? 0),
			'continued' => $continued
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
