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
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\Query\Order;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Taxonomy;
use Blush\Http\Response;
use Blush\Http\Status as HttpStatus;
use Blush\Support\Slug;

/**
 * Answers `GET {path}/api/references/{type}` (D-242's picker, D-281):
 * what a reference field to `type` can point at, for anyone who edits
 * entries, whether or not they may edit the entries listed (an author
 * files a post under a category they can't edit). Each item is the
 * `slug` a reference stores, its `title`, its `status`, its `parent`'s
 * slug (or `null`), and for a taxonomy's terms how many published
 * entries use it (`uses`; else `null`). A taxonomy's **virtual terms**
 * (slugs entries use with no file) are items too, marked `virtual`, and
 * a taxonomy answers `create: true`, since a slug with no term becomes
 * one as it's typed; other types' references must name an entry.
 *
 * A hierarchical taxonomy answers every term, in tree order (each term
 * followed by its children, siblings by title) with its `depth`, since
 * its picker shows the whole tree. Other types answer the items whose
 * title or slug has `search` in it, by title, at most `limit` (20 by
 * default, at most 100), with the `total` found. `slugs` (comma
 * separated) are always answered too, found or not, so a field can show
 * what it holds: a slug with nothing behind it is `missing`. A type's
 * landing page isn't something to point at, so it's left out.
 *
 * With `for` (a content type's name), a taxonomy answers only the terms
 * that type's entries use, in any status, among the entries the account
 * may edit, for the entry list's filters (D-303); a hierarchical
 * taxonomy keeps a used term's parents, so the tree holds together.
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

	public function __construct(
		private ContentRepository $content,
		private ContentTypes $types,
		private Permissions $permissions
	) {}

	public function __invoke(ServerRequestInterface $request, string $type): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account) {
			return self::json(['error' => 'Sign in first.'], HttpStatus::Unauthorized);
		}

		if (! $this->permissions->can($account, Capability::ContentEdit)) {
			return self::json(['error' => 'Your account can\'t edit content.'], HttpStatus::Forbidden);
		}

		$contentType = $this->types->find($type);

		if ($contentType === null) {
			return self::json(['error' => 'There is no such content type.'], HttpStatus::NotFound);
		}

		$params = $request->getQueryParams();
		$search = $params['search'] ?? '';
		$slugs  = $params['slugs'] ?? '';
		$limit  = $params['limit'] ?? (string) self::LIMIT;
		$for    = $params['for'] ?? '';

		if (! is_string($search) || ! is_string($slugs) || ! is_string($limit) || ! is_string($for)) {
			return self::json(['error' => '"search", "slugs", "limit", and "for" must be text.'], HttpStatus::BadRequest);
		}

		if ($for !== '' && ! $this->types->has($for)) {
			return self::json(['error' => sprintf('There is no "%s" content type.', $for)], HttpStatus::BadRequest);
		}

		$limit = ctype_digit($limit) ? (int) $limit : 0;

		if ($limit < 1 || $limit > self::MAX_LIMIT) {
			return self::json(['error' => sprintf('"limit" must be a whole number from 1 to %d.', self::MAX_LIMIT)], HttpStatus::BadRequest);
		}

		$taxonomy = $contentType->hasTerms();
		$counts   = $taxonomy ? $this->content->termCounts($type) : [];
		$entries  = $this->content->query()->any()->type($type)->withLanding(false)->orderBy('title', Order::Asc)->limit(null)->get()->all();
		$items    = [];
		$used     = $taxonomy && $for !== ''
			? array_filter($this->content->termCounts($type, $this->permissions->restrict($account, Capability::ContentEdit, $this->content->query()->any()->type($for))))
			: null;

		foreach ($entries as $entry) {
			$items[$entry->key] = $this->describe($entry, $counts, $taxonomy);
		}

		// A taxonomy's virtual terms: slugs in use with no file.
		foreach (array_keys($counts + ($used ?? [])) as $slug) {
			$slug = (string) $slug;

			if (! isset($items[$slug])) {
				$term         = $this->content->term($type, $slug);
				$items[$slug]  = ['slug' => $slug, 'title' => $term->title ?? $slug, 'status' => 'published', 'parent' => null, 'uses' => $counts[$slug] ?? 0, 'depth' => null, 'virtual' => true, 'missing' => false];
			}
		}

		if ($used !== null) {
			$items = self::inUse($items, array_map(strval(...), array_keys($used)));
		}

		$tree  = $contentType instanceof Taxonomy && $contentType->hierarchical;
		$found = $tree ? self::tree($items) : self::matching($items, $search);
		$total = count($found);
		$shown = $tree ? $found : array_slice($found, 0, $limit);

		// The field's own slugs, found or not.
		$held  = array_values(array_filter(array_map(Slug::from(...), explode(',', $slugs)), static fn (string $slug): bool => $slug !== ''));
		$named = array_column($shown, 'slug');

		foreach ($held as $slug) {
			if (! in_array($slug, $named, true)) {
				$shown[] = $items[$slug] ?? ['slug' => $slug, 'title' => $slug, 'status' => null, 'parent' => null, 'uses' => null, 'depth' => null, 'virtual' => false, 'missing' => true];
				$named[] = $slug;
			}
		}

		return self::json([
			'type'   => $type,
			'create' => $taxonomy,
			'tree'   => $tree,
			'search' => trim($search),
			'total'  => $total,
			'items'  => $shown
		]);
	}

	/**
	 * @param  array<string, int>   $counts Term uses by slug.
	 * @return array<string, mixed>
	 */
	private function describe(Entry $entry, array $counts, bool $taxonomy): array
	{
		return [
			'slug'    => $entry->key,
			'title'   => $entry->title,
			'status'  => $entry->status->value,
			'parent'  => $this->content->parentKey($entry->type->name, $entry->key),
			'uses'    => $taxonomy ? ($counts[$entry->key] ?? 0) : null,
			'depth'   => null,
			'virtual' => false,
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
	 * The items whose title or slug has the search in it (any case), by
	 * title.
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

		usort($found, static fn (array $a, array $b): int => strnatcasecmp(self::text($a['title'] ?? ''), self::text($b['title'] ?? '')));

		return $found;
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
			usort($list, static fn (string $a, string $b): int => strnatcasecmp(self::text($items[$a]['title'] ?? ''), self::text($items[$b]['title'] ?? '')));
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
