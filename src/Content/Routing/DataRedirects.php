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

use Override;
use Blush\Core\Paths;
use Blush\Routing\InvalidRoute;
use Blush\Routing\Redirect;
use Blush\Routing\RedirectSource;
use Blush\Storage\File\FileLayout;
use Blush\Storage\Record\LocatingStore;
use Blush\Storage\Record\RecordException;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageArea;
use Blush\Storage\StorageException;

/**
 * The site owner's redirects: the `redirects` table (D-678), a row per
 * redirect, keyed by its `from` path (a path key, D-679), with its `to`
 * and `status` (301 when it has none). On files the table is one file,
 * `user/data/redirects.json`, a list:
 *
 * ```json
 * [
 *     {"from": "/old-about", "to": "/about"},
 *     {"from": "/blog/{slug}", "to": "/archives/{slug}"},
 *     {"from": "/promo", "to": "https://example.com/sale", "status": 302}
 * ]
 * ```
 *
 * Paths are route patterns, as in `config/routes.php`.
 */
final readonly class DataRedirects implements RedirectSource
{
	/**
	 * The table's name, and its file's under `user/data`.
	 */
	public const string TABLE = 'redirects';

	public function __construct(
		private RecordStores $stores
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
		$table = self::table();

		try {
			$store   = $this->stores->store($table);
			$records = $store->select($table, new RecordQuery())->records;
		} catch (RecordException | StorageException $e) {
			throw new InvalidRoute(sprintf('The redirects can\'t be read: %s', $e->getMessage()), previous: $e);
		}

		$redirects = [];

		foreach ($records as $record) {
			try {
				$redirects[] = Redirect::fromArray($record->fields);
			} catch (InvalidRoute $e) {
				$from  = $record->fields['from'] ?? null;
				$where = $store instanceof LocatingStore ? $store->location($table, is_string($from) ? $from : $record->id) : self::TABLE;

				throw new InvalidRoute(sprintf('%s: %s', $where, $e->getMessage()), previous: $e);
			}
		}

		return $redirects;
	}
}
