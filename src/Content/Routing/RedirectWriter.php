<?php

/**
 * Redirect writer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Routing;

use Psr\Clock\ClockInterface;
use Blush\Core\AppConfig;
use Blush\Routing\InvalidRoute;
use Blush\Routing\RouteCache;
use Blush\Storage\Record\KeyedTable;
use Blush\Storage\Record\RecordException;
use Blush\Storage\Record\RecordStores;

/**
 * Writes the redirects a move leaves behind (D-680): when a published
 * entry's address changes in the admin, its old address redirects to the
 * new one, as a row in the `redirects` table (`DataRedirects`).
 *
 * For each move, a permanent redirect from the old address replaces any
 * row from it; rows that led to the old address lead to the new one, so
 * no redirect chains; and a row from the new address goes, since the
 * entry answers there now. A compiled route table is written again, so
 * the redirects work in production without a `cache:compile`.
 */
final readonly class RedirectWriter
{
	public function __construct(
		private RecordStores $stores,
		private ClockInterface $clock,
		private RouteCache $routes,
		private AppConfig $app
	) {}

	/**
	 * Redirects old addresses to new ones. An address that isn't a path,
	 * or that didn't change, is left out.
	 *
	 * @param  array<string, string> $moves New addresses, by old address.
	 * @throws RecordException When the redirects can't be written.
	 * @throws InvalidRoute When the compiled route table can't be written.
	 */
	public function moved(array $moves): void
	{
		$table = DataRedirects::table();
		$moves = array_filter(
			$moves,
			static fn (string $to, string $from): bool => $from !== $to && $table->isKey($from) && $table->isKey($to),
			ARRAY_FILTER_USE_BOTH
		);

		if ($moves === []) {
			return;
		}

		$rows = new KeyedTable($this->stores, $table, $this->clock);

		$rows->transaction(static function () use ($rows, $moves): void {
			foreach ($moves as $from => $to) {
				$rows->delete($to);

				foreach ($rows->all() as $path => $row) {
					if (($row['to'] ?? null) === $from && $path !== $from) {
						$rows->save($path, [...$row, 'to' => $to]);
					}
				}

				$rows->save($from, ['to' => $to]);
			}
		});

		if (! $this->app->environment->isDevelopment() && is_file($this->routes->path())) {
			$this->routes->write();
		}
	}
}
