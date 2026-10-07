<?php

/**
 * Content repository interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

use Blush\Content\Entry\Entry;
use Blush\Content\Query\Query;
use Blush\Content\Query\QueryRunner;

/**
 * The one place the rest of the framework gets content from (D-003):
 * entries by id, source path, or key, terms (real or virtual), and
 * queries.
 *
 *     $post  = $content->find('0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74');
 *     $post  = $content->findPath('_posts/2003-04-15.welcome.md');
 *     $about = $content->named('page', 'about');
 *     $posts = $content->query()->type('post')->orderBy('published', Order::Desc)->get();
 */
interface ContentRepository extends QueryRunner
{
	/**
	 * Returns a new query that runs against this repository.
	 */
	public function query(): Query;

	/**
	 * Returns an entry by its id (D-477), whatever its status. When files
	 * share an id, the first by path is found.
	 */
	public function find(string $id): ?Entry;

	/**
	 * Returns an entry by its source path, whatever its status.
	 */
	public function findPath(string $path): ?Entry;

	/**
	 * Returns an entry by type and key (see `IndexRecord`), in a language
	 * (by code; the site's default language when `null`), whatever its
	 * status. A landing page's key is `''`.
	 */
	public function named(string $type, string $key, ?string $language = null): ?Entry;

	/**
	 * Returns a taxonomy term: its entry when it has a file in the
	 * language (the default when `null`), by its key there or by its
	 * original's, a virtual entry when it's referenced but has none, or
	 * `null`.
	 */
	public function term(string $taxonomy, string $slug, ?string $language = null): ?Entry;

	/**
	 * Returns an entry and its translations (D-455), by language code,
	 * the entry's own included.
	 *
	 * @return array<string, Entry>
	 */
	public function translations(Entry $entry): array;

	/**
	 * Returns an entry's translation in a language (itself for its own
	 * language), whatever its status, or `null`.
	 */
	public function translation(Entry $entry, string $language): ?Entry;

	/**
	 * Returns the key of an entry's parent, by the entry's type and key,
	 * when the parent has a file (in the default language unless another
	 * is given); `null` otherwise. Cheaper than `parent()` when only the
	 * key is needed, such as for a hierarchical term's URL.
	 */
	public function parentKey(string $type, string $key, ?string $language = null): ?string;

	/**
	 * Returns an entry's parent in its own type (see
	 * `ContentType::parentKey()`), whatever its status, or `null` when it
	 * has none or the parent has no file.
	 */
	public function parent(Entry $entry): ?Entry;

	/**
	 * Returns the entries whose parent is this one, whatever their
	 * status, by title.
	 *
	 * @return list<Entry>
	 */
	public function children(Entry $entry): array;

	/**
	 * Returns the entries on either side of one in a listing: by default
	 * its type's own (in the type's order, so newest first for a
	 * collection), in the entry's language. `before` comes just before it
	 * in that order and `after` just after; either is `null` at an end,
	 * and both are for an entry the listing doesn't hold. Give a query to
	 * walk another listing (its limit and offset aside).
	 *
	 * @return array{before: ?Entry, after: ?Entry}
	 */
	public function neighbors(Entry $entry, ?Query $query = null): array;

	/**
	 * Returns how many listed entries reference each term of a taxonomy,
	 * by slug, including virtual terms; or, given a query, how many of the
	 * entries it finds do (its limit and offset aside).
	 *
	 * @return array<string, int>
	 */
	public function termCounts(string $taxonomy, ?Query $query = null): array;

	/**
	 * Returns the entries that ask for redirects, keyed by each path in
	 * their `redirect_from` front matter (the first entry to claim a path
	 * keeps it).
	 *
	 * @return array<string, Entry>
	 */
	public function redirects(): array;
}
