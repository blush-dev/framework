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
 * entries by ID or by key, terms (real or virtual), and queries.
 *
 *     $post  = $content->find('_posts/2003-04-15.welcome.md');
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
	 * Returns an entry by ID (its source path), whatever its status.
	 */
	public function find(string $id): ?Entry;

	/**
	 * Returns an entry by type and key (see `IndexRecord`), in a locale
	 * (the site's by default), whatever its status. A landing page's key
	 * is `''`.
	 */
	public function named(string $type, string $key, ?string $locale = null): ?Entry;

	/**
	 * Returns a taxonomy term: its entry when it has a file, a virtual
	 * entry when it's referenced but has none, or `null`.
	 */
	public function term(string $taxonomy, string $slug): ?Entry;

	/**
	 * Returns how many listed entries reference each term of a taxonomy,
	 * by slug, including virtual terms.
	 *
	 * @return array<string, int>
	 */
	public function termCounts(string $taxonomy): array;

	/**
	 * Returns the entries that ask for redirects, keyed by each path in
	 * their `redirect_from` front matter (the first entry to claim a path
	 * keeps it).
	 *
	 * @return array<string, Entry>
	 */
	public function redirects(): array;
}
