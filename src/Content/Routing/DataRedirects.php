<?php

/**
 * Data redirects.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Routing;

use Closure;
use Override;
use Blush\Container\Attributes\Defer;
use Blush\Content\Entries;
use Blush\Core\Paths;
use Blush\Routing\InvalidRoute;
use Blush\Routing\Redirect;
use Blush\Routing\RedirectSource;
use Blush\Storage\File\FileLayout;
use Blush\Storage\Record\RecordException;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageArea;

/**
 * The site owner's redirects: the `redirects` table (D-678), a row per
 * redirect, keyed by its `from` path (a path key, D-679), with its `to`
 * and `status` (301 when it has none). On files the table is one file,
 * `user/data/redirects.json`, a list:
 *
 * ```json
 * [
 *     {"from": "/old-about", "entry": "0199b6e2-…"},
 *     {"from": "/blog/{slug}", "to": "/archives/{slug}"},
 *     {"from": "/promo", "to": "https://example.com/sale", "status": 302}
 * ]
 * ```
 *
 * Paths are route patterns, as in `config/routes.php`. A row may lead to
 * an entry by its id in place of `to` (D-686), so it follows the entry
 * wherever it moves: it redirects to the entry's address while the entry
 * is live, and is left out of the route table while it isn't (in the
 * trash, a draft, hidden, or gone), so the old address is a 404. The
 * rows' `added`, `by`, and `via` are the admin's (`RedirectRow`).
 */
final readonly class DataRedirects implements RedirectSource
{
	/**
	 * The table's name, and its file's under `user/data`.
	 */
	public const string TABLE = 'redirects';

	/**
	 * @param Closure(): Entries $content Deferred: only rows leading to entries need it.
	 */
	public function __construct(
		private Redirects $rows,
		private ContentUrls $urls,
		#[Defer(Entries::class)] private Closure $content
	) {}

	/**
	 * The redirects' table.
	 */
	public static function table(): Table
	{
		return new Table(self::TABLE, StorageArea::Data, key: 'from', fields: ['from'], pathKey: true);
	}

	/**
	 * How the filesystem driver keeps the table: one file of them all.
	 */
	public static function layout(Paths $paths): FileLayout
	{
		return FileLayout::oneFile("{$paths->data}/" . self::TABLE . '.json');
	}

	/**
	 * @inheritDoc
	 * @return list<Redirect>
	 * @throws InvalidRoute
	 */
	#[Override]
	public function redirects(): iterable
	{
		try {
			$rows = $this->rows->all();
		} catch (RecordException $e) {
			throw new InvalidRoute(sprintf('The redirects can\'t be read: %s', $e->getMessage()), previous: $e);
		}

		$redirects = [];

		foreach ($rows as $row) {
			$to = $this->target($row);

			if ($to !== null) {
				$redirects[] = new Redirect($row->from, $to, $row->status);
			}
		}

		return $redirects;
	}

	/**
	 * Where a row leads now: its `to`, or its entry's address while the
	 * entry is live, else `null`.
	 */
	public function target(RedirectRow $row): ?string
	{
		if ($row->entry === null) {
			return $row->to;
		}

		$entry = ($this->content)()->find($row->entry);

		return $entry !== null && $entry->isPublished() && $entry->isRoutable() ? $this->urls->entry($entry) : null;
	}
}
