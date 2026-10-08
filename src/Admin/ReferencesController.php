<?php

/**
 * Admin references controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\ContentAction;
use Blush\Auth\Permissions;
use Blush\Content\ContentRepository;
use Blush\Content\Index\ContentIndex;
use Blush\Content\Relation\Relation;
use Blush\Content\Relation\RelationLimits;
use Blush\Content\Relation\Relations;
use Blush\Content\Status;
use Blush\Content\Entry\Entry;
use Blush\Content\Entry\Position;
use Blush\Storage\Record\Order;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Tree;
use Blush\Http\Response;
use Blush\Http\Status as HttpStatus;
use Blush\Support\Slug;

/**
 * Answers `GET {path}/api/references/{type}` (D-242's picker, D-281):
 * what a reference field to `type` can point at, for anyone who edits
 * entries, whether or not they may edit the entries listed (an author
 * files a post under a category they can't edit). Each item is the
 * `slug` a reference stores, its `title`, its `status`, its `parent`'s
 * slug (or `null`), for terms how many published entries use it
 * (`uses`; else `null`), and its `image` and publish `date` (`Y-m-d`)
 * or `null`, for the picker's cards (D-599). A type a classify relation files entries
 * under answers `create` as the relation says, since the picker writes
 * a new term's file as it's typed (D-584, D-593); other types'
 * references must name an entry.
 *
 * A hierarchical collection answers every entry, in tree order (each term
 * followed by its children, siblings by title) with its `depth`, since
 * its picker shows the whole tree. With `tree=1`, a tree's pages are
 * answered the same way, for picking a new page's parent (D-408). Other types answer the items whose
 * title or slug has `search` in it, by title, at most `limit` (20 by
 * default, at most 100), with the `total` found. `slugs` (comma
 * separated) are always answered too, found or not, so a field can show
 * what it holds: a slug with nothing behind it is `missing`. A type's
 * landing page isn't something to point at, so it's left out, nor are
 * the site's error pages (D-411).
 *
 * With `for` (a content type's name), a term type answers only the terms
 * that type's entries use, in any status, among the entries the account
 * may edit, for the entry list's filters (D-303); a hierarchical one
 * keeps a used term's parents, so the tree holds together.
 *
 * **The relation pickers** (D-607) ask with `upto` (50): with no search
 * and that many candidates or fewer, every one is answered (`whole`), in
 * tree order for a nesting type; with more, none are, so a picker never
 * holds the whole set of a large site. A search answers its matches,
 * ranked: titles starting with it first, then a word in the title, then
 * the rest, by title within each, each with its `path` (its ancestors'
 * titles, `Mains › Pasta`) when the type nests, at most `limit`, with
 * the `total` found. `total` with no search is how many candidates
 * there are.
 *
 * - `suggest` asks for what a picker offers before anything's typed,
 *   as `suggested` (`suggestions` of them, 5 by default): the most
 *   used terms (`uses`, the recently edited for a type that isn't
 *   terms), the recently edited (`edited`), or the targets most
 *   recently linked through a relation (`recent`, with its key in
 *   `from`: `recipe.cooks`), from the entries of its type last changed.
 * - `except` leaves out a slug (the entry being edited), and with
 *   `branch=1` the entries under it too (a term's parent), answering
 *   how many of those were left out (`excluded`).
 * - A held slug that names a trashed entry comes back with `status`
 *   `trash`; one that names nothing is `missing`, with the `closest`
 *   candidate (`slug`, `title`) when one is near enough to be a typo.
 * - With `from` naming a relation whose inverse has a `max` (a
 *   collection features 12 recipes at most), it's answered as
 *   `inverseMax`, and each item says how many entries name it through
 *   the relation (`taken`, drafts too, not the trash), so a picker shows
 *   a full target before it's picked (D-608). Otherwise `inverseMax` is
 *   `null`, and so is each `taken`.
 */
final readonly class ReferencesController
{
	/**
	 * How many items a search answers unless `limit` says otherwise.
	 */
	public const int LIMIT = 20;

	/**
	 * The most items a search answers.
	 */
	public const int MAX_LIMIT = 100;

	/**
	 * How many suggestions are answered unless `suggestions` says
	 * otherwise, and the most.
	 */
	public const int SUGGESTIONS = 5;

	public const int MAX_SUGGESTIONS = 20;

	/**
	 * How many of a type's last-changed entries `recent` looks through.
	 */
	private const int RECENT_SOURCES = 200;

	public function __construct(
		private ContentRepository $content,
		private ContentTypes $types,
		private Permissions $permissions,
		private ContentIndex $index,
		private Relations $relations,
		private RelationLimits $limits
	) {}

	public function __invoke(ServerRequestInterface $request, string $type): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account) {
			return self::json(['error' => 'Sign in first.'], HttpStatus::Unauthorized);
		}

		if (! $this->permissions->can($account, ContentAction::Edit)) {
			return self::json(['error' => 'Your account can\'t edit content.'], HttpStatus::Forbidden);
		}

		$contentType = $this->types->find($type);

		if ($contentType === null) {
			return self::json(['error' => 'There is no such content type.'], HttpStatus::NotFound);
		}

		$params = $request->getQueryParams();
		$text   = [];

		foreach (['search', 'slugs', 'limit', 'for', 'tree', 'upto', 'suggest', 'suggestions', 'from', 'except', 'branch'] as $name) {
			$value = $params[$name] ?? '';

			if (! is_string($value)) {
				return self::json(['error' => sprintf('"%s" must be text.', $name)], HttpStatus::BadRequest);
			}

			$text[$name] = $value;
		}

		$search = $text['search'];
		$for    = $text['for'];

		if ($for !== '' && ! $this->types->has($for)) {
			return self::json(['error' => sprintf('There is no "%s" content type.', $for)], HttpStatus::BadRequest);
		}

		$limit = self::whole($text['limit'] === '' ? (string) self::LIMIT : $text['limit'], 1, self::MAX_LIMIT);
		$upto  = $text['upto'] === '' ? null : self::whole($text['upto'], 1, self::MAX_LIMIT);
		$few   = self::whole($text['suggestions'] === '' ? (string) self::SUGGESTIONS : $text['suggestions'], 1, self::MAX_SUGGESTIONS);

		if ($limit === null || ($upto === null && $text['upto'] !== '') || $few === null) {
			return self::json(['error' => sprintf('"limit" and "upto" must be whole numbers from 1 to %d, and "suggestions" from 1 to %d.', self::MAX_LIMIT, self::MAX_SUGGESTIONS)], HttpStatus::BadRequest);
		}

		$suggest = $text['suggest'];

		if (! in_array($suggest, ['', 'uses', 'edited', 'recent'], true)) {
			return self::json(['error' => '"suggest" must be "uses", "edited", or "recent".'], HttpStatus::BadRequest);
		}

		$from = $text['from'] === '' ? null : $this->relations->byKey($text['from']);

		if ($suggest === 'recent' && ($from === null || ! str_contains($text['from'], '.'))) {
			return self::json(['error' => '"recent" needs a relation\'s key in "from".'], HttpStatus::BadRequest);
		}

		$taxonomy   = $this->types->isTermType($type);
		$counts     = $taxonomy ? $this->content->termCounts($type) : [];
		$entries    = $this->content->query()->any()->type($type)->withLanding(false)->orderBy('title', Order::Asc)->limit(null)->get()->all();
		$inverseMax = $from === null || $from->inverse === false ? null : $from->inverse->max;
		$taken      = $from === null || $inverseMax === null ? null : $this->limits->taken($from, array_values(array_filter(array_map(static fn (Entry $entry): ?string => $entry->id, $entries))));
		$items    = [];
		$used     = $taxonomy && $for !== ''
			? array_filter($this->content->termCounts($type, $this->permissions->restrict($account, ContentAction::Edit, $this->content->query()->any()->type($for))))
			: null;

		foreach ($entries as $entry) {
			// Error pages (D-411) are the site's, not pages to point at.
			if (ErrorPage::status($entry) === null) {
				$items[$entry->key] = $this->describe($entry, $counts, $taxonomy, $taken);
			}
		}

		if ($used !== null) {
			$items = self::inUse($items, array_map(strval(...), array_keys($used)));
		}

		$nests = $this->types->nestsByParent($type);

		if ($nests || $contentType instanceof Tree) {
			$items = self::withPaths($items);
		}

		[$items, $excluded] = self::except($items, Slug::from($text['except']), $text['branch'] === '1');

		$candidates = count($items);
		$whole      = $upto !== null && trim($search) === '' && $candidates <= $upto;
		$tree       = $upto === null ? $nests || ($contentType instanceof Tree && $text['tree'] === '1') : $whole && $nests;

		if ($tree) {
			$found = self::tree($items);
		} elseif ($upto !== null && ! $whole && trim($search) === '') {
			$found = [];
		} else {
			$found = self::matching($items, $search);
		}

		$total = $upto !== null && trim($search) === '' ? $candidates : count($found);
		$shown = $tree || $whole ? $found : array_slice($found, 0, $limit);

		// The field's own slugs, found or not.
		$held  = array_values(array_filter(array_map(Slug::from(...), explode(',', $text['slugs'])), static fn (string $slug): bool => $slug !== ''));
		$named = array_column($shown, 'slug');

		foreach ($held as $slug) {
			if (! in_array($slug, $named, true)) {
				$shown[] = $items[$slug] ?? $this->unlisted($type, $slug, $items, $counts, $taxonomy);
				$named[] = $slug;
			}
		}

		$suggested = $suggest === '' ? [] : $this->suggested($suggest, $type, $taxonomy, $items, $from === null ? null : [strstr($text['from'], '.', true) ?: '', $from], $few);

		return self::json([
			'type'       => $type,
			'create'     => $this->types->classification($type)->create ?? false,
			'inverseMax' => $inverseMax,
			'tree'       => $tree,
			'whole'      => $whole,
			'search'     => trim($search),
			'total'      => $total,
			'excluded'   => $excluded,
			'items'      => array_map(self::item(...), $shown),
			'suggested'  => array_map(self::item(...), $suggested)
		]);
	}

	/**
	 * Returns a whole number from text when it's within bounds, else
	 * `null`.
	 */
	private static function whole(string $text, int $least, int $most): ?int
	{
		$number = ctype_digit($text) ? (int) $text : 0;

		return $number < $least || $number > $most ? null : $number;
	}

	/**
	 * Returns an item as it's answered, without what's only used to
	 * order it.
	 *
	 * @param  array<string, mixed> $item
	 * @return array<string, mixed>
	 */
	private static function item(array $item): array
	{
		unset($item['position']);

		return $item;
	}

	/**
	 * Returns a held slug that isn't a candidate: a trashed entry, as it
	 * is, or a missing one, with the candidate closest to it.
	 *
	 * @param  array<string, array<string, mixed>> $items
	 * @param  array<string, int>                  $counts
	 * @return array<string, mixed>
	 */
	private function unlisted(string $type, string $slug, array $items, array $counts, bool $taxonomy): array
	{
		$trashed = $this->content->query()->any()->status(Status::Trash)->type($type)->names($slug)->first();

		if ($trashed !== null) {
			return $this->describe($trashed, $counts, $taxonomy);
		}

		return ['slug' => $slug, 'title' => $slug, 'status' => null, 'parent' => null, 'uses' => null, 'depth' => null, 'missing' => true, 'closest' => self::closest($slug, $items)];
	}

	/**
	 * Returns the candidate a missing slug most likely meant: the one
	 * whose slug is fewest edits from it, when that's few enough to be a
	 * typo (as `Console` suggests commands).
	 *
	 * @param  array<string, array<string, mixed>> $items
	 * @return ?array{slug: string, title: string}
	 */
	private static function closest(string $slug, array $items): ?array
	{
		$best     = null;
		$distance = max(2, intdiv(strlen($slug), 3)) + 1;

		foreach ($items as $key => $item) {
			$key  = (string) $key;
			$edit = levenshtein($slug, $key);

			if ($edit < $distance) {
				$best     = ['slug' => $key, 'title' => self::text($item['title'] ?? '')];
				$distance = $edit;
			}
		}

		return $best;
	}

	/**
	 * Returns what a picker offers before anything's typed (D-607).
	 *
	 * @param  'uses'|'edited'|'recent'            $how
	 * @param  array<string, array<string, mixed>> $items
	 * @param  ?array{string, Relation}            $from  The relation's source type, and the relation.
	 * @return list<array<string, mixed>>
	 */
	private function suggested(string $how, string $type, bool $taxonomy, array $items, ?array $from, int $few): array
	{
		if ($how === 'uses' && $taxonomy) {
			$list = array_values(array_filter($items, static fn (array $item): bool => ($item['uses'] ?? 0) > 0));

			usort($list, static fn (array $a, array $b): int => [$b['uses'], self::text($a['title'] ?? '')] <=> [$a['uses'], self::text($b['title'] ?? '')]);

			return array_slice($list, 0, $few);
		}

		if ($how === 'recent' && $from !== null) {
			return $this->recent($type, $items, $from[0], $from[1], $few);
		}

		$found = [];

		foreach ($this->content->query()->any()->type($type)->withLanding(false)->orderBy('updated', Order::Desc)->limit(null)->get()->all() as $entry) {
			if (isset($items[$entry->key])) {
				$found[] = $items[$entry->key];

				if (count($found) >= $few) {
					break;
				}
			}
		}

		return $found;
	}

	/**
	 * Returns the candidates most recently linked through a relation: the
	 * targets of the entries of its type last changed, in that order.
	 *
	 * @param  array<string, array<string, mixed>> $items
	 * @return list<array<string, mixed>>
	 */
	private function recent(string $type, array $items, string $source, Relation $from, int $few): array
	{
		$graph   = $this->index->snapshot()->graph();
		$sources = $this->content->query()->any()->type($source)->orderBy('updated', Order::Desc)->limit(self::RECENT_SOURCES)->get()->all();
		$found   = [];

		foreach ($sources as $entry) {
			foreach ($entry->id === null ? [] : $graph->targets($entry->id, $from->name) as $id) {
				$target = isset($found[$id]) ? null : $this->content->find($id);

				if ($target !== null && $target->type->name === $type && isset($items[$target->key])) {
					$found[$id] = $items[$target->key];

					if (count($found) >= $few) {
						return array_values($found);
					}
				}
			}
		}

		return array_values($found);
	}

	/**
	 * Leaves out a slug, and the items under it with `$branch`, answering
	 * what's left and how many were left out besides it.
	 *
	 * @param  array<string, array<string, mixed>> $items
	 * @return array{array<string, array<string, mixed>>, int}
	 */
	private static function except(array $items, string $slug, bool $branch): array
	{
		if ($slug === '') {
			return [$items, 0];
		}

		$out  = [$slug => true];
		$grew = $branch;

		while ($grew) {
			$grew = false;

			foreach ($items as $key => $item) {
				$parent = $item['parent'] ?? null;

				if (is_string($parent) && isset($out[$parent]) && ! isset($out[(string) $key])) {
					$out[(string) $key] = true;
					$grew               = true;
				}
			}
		}

		return [array_diff_key($items, $out), count($out) - 1];
	}

	/**
	 * Gives each item of a nesting type its `path`: its ancestors' titles,
	 * from the top (`Mains › Pasta`), or `null` at the top.
	 *
	 * @param  array<string, array<string, mixed>> $items
	 * @return array<string, array<string, mixed>>
	 */
	private static function withPaths(array $items): array
	{
		foreach ($items as $slug => $item) {
			$titles = [];
			$seen   = [(string) $slug => true];
			$parent = $item['parent'] ?? null;

			while (is_string($parent) && isset($items[$parent]) && ! isset($seen[$parent])) {
				$seen[$parent] = true;
				array_unshift($titles, self::text($items[$parent]['title'] ?? ''));
				$parent = $items[$parent]['parent'] ?? null;
			}

			$items[$slug]['path'] = $titles === [] ? null : implode(' › ', $titles);
		}

		return $items;
	}

	/**
	 * @param  array<string, int>   $counts Term uses by slug.
	 * @param  ?array<string, int>  $taken  How many entries name each, by id, when it's counted.
	 * @return array<string, mixed>
	 */
	private function describe(Entry $entry, array $counts, bool $taxonomy, ?array $taken = null): array
	{
		return [
			'slug'    => $entry->key,
			'title'   => $entry->title,
			'status'  => $entry->status->value,
			'parent'  => $this->content->parentKey($entry->type->name, $entry->key),
			'position' => $entry->type instanceof Tree ? Position::of($entry) : null,
			'uses'    => $taxonomy ? ($counts[$entry->key] ?? 0) : null,
			'image'   => is_string($image = $entry->field('image')) && $image !== '' ? $image : null,
			'date'    => $entry->published?->format('Y-m-d'),
			'depth'   => null,
			'taken'   => $taken === null ? null : $taken[(string) $entry->id] ?? 0,
			'missing' => false
		];
	}

	/**
	 * The items a type's entries use, and the parents of each, so a tree
	 * keeps its branches.
	 *
	 * @param  array<string, array<string, mixed>> $items
	 * @param  list<string>                        $used
	 * @return array<string, array<string, mixed>>
	 */
	private static function inUse(array $items, array $used): array
	{
		$keep = [];

		foreach ($used as $slug) {
			// Up the parents, stopping at one already kept (or a loop).
			while (isset($items[$slug]) && ! isset($keep[$slug])) {
				$keep[$slug] = true;
				$parent      = $items[$slug]['parent'] ?? null;

				if (! is_string($parent)) {
					break;
				}

				$slug = $parent;
			}
		}

		return array_intersect_key($items, $keep);
	}

	/**
	 * The items whose title or slug has the search in it (any case): those
	 * whose title starts with it first, then those with a word starting
	 * with it, then the rest, by title within each (D-607).
	 *
	 * @param  array<string, array<string, mixed>> $items
	 * @return list<array<string, mixed>>
	 */
	private static function matching(array $items, string $search): array
	{
		$search = mb_strtolower(trim($search));
		$found  = array_values(array_filter($items, static fn (array $item): bool => $search === ''
			|| str_contains(mb_strtolower(self::text($item['title'] ?? '')), $search)
			|| str_contains(self::text($item['slug'] ?? ''), $search)));

		usort($found, static fn (array $a, array $b): int => [self::rank($a, $search), 0] <=> [self::rank($b, $search), strnatcasecmp(self::text($a['title'] ?? ''), self::text($b['title'] ?? ''))]);

		return $found;
	}

	/**
	 * How well an item's title matches a search: 0 when it starts with
	 * it, 1 when a word in it does, 2 otherwise.
	 *
	 * @param array<string, mixed> $item
	 */
	private static function rank(array $item, string $search): int
	{
		$title = mb_strtolower(self::text($item['title'] ?? ''));

		return match (true) {
			$search === '' || str_starts_with($title, $search)                               => 0,
			preg_match('/(?<![\p{L}\p{N}])' . preg_quote($search, '/') . '/u', $title) === 1 => 1,
			default                                                                          => 2
		};
	}

	/**
	 * The items in tree order, each with its depth. An item whose parent
	 * isn't among them is at the top; items in a loop of parents come
	 * last, at the top.
	 *
	 * @param  array<string, array<string, mixed>> $items
	 * @return list<array<string, mixed>>
	 */
	private static function tree(array $items): array
	{
		$children = [];

		foreach ($items as $slug => $item) {
			$parent = is_string($item['parent'] ?? null) && isset($items[$item['parent']]) && $item['parent'] !== $slug ? $item['parent'] : '';

			$children[$parent][] = $slug;
		}

		foreach ($children as &$list) {
			// A tree's pages by position (D-412); terms by title (D-304).
			usort($list, static fn (string $a, string $b): int => Position::compare(
				is_int($items[$a]['position'] ?? null) ? $items[$a]['position'] : null,
				self::text($items[$a]['title'] ?? ''),
				is_int($items[$b]['position'] ?? null) ? $items[$b]['position'] : null,
				self::text($items[$b]['title'] ?? '')
			));
		}

		unset($list);

		$ordered = [];
		$walk    = static function (string $parent, int $depth) use (&$walk, &$ordered, $children, $items): void {
			foreach ($children[$parent] ?? [] as $slug) {
				if (isset($ordered[$slug])) {
					continue;
				}

				$ordered[$slug] = ['depth' => $depth] + $items[$slug];
				$walk($slug, $depth + 1);
			}
		};

		$walk('', 0);

		foreach ($items as $slug => $item) {
			$ordered[$slug] ??= ['depth' => 0] + $item;
		}

		return array_values($ordered);
	}

	private static function text(mixed $value): string
	{
		return is_string($value) ? $value : '';
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, HttpStatus $status = HttpStatus::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}
}
