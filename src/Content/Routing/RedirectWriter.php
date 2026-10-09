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
use Blush\Routing\InvalidRoute;
use Blush\Storage\Record\RecordException;

/**
 * Writes the redirects a move leaves behind (D-680, D-686): when a
 * published entry's address changes in the admin, its old address
 * redirects to the entry, as a row in the `redirects` table that names
 * the entry by its id, so it follows the entry through later moves.
 *
 * For each move, a permanent redirect from the old address replaces any
 * row from it; rows that led to the old address as a path lead to the
 * entry, so there are no chains; and a row from the new address goes,
 * since the entry answers there now (so renaming back removes the row).
 * Each row says when it was added, by whom, and how (`RedirectOrigin`).
 * An entry without an id gets a row to its new path, as before.
 */
final readonly class RedirectWriter
{
	public function __construct(
		private Redirects $rows,
		private ClockInterface $clock
	) {}

	/**
	 * Redirects old addresses to entries' new ones. A move whose
	 * addresses aren't paths, or didn't change, is left out.
	 *
	 * @param  list<array{from: string, to: string, entry: ?string, via: RedirectOrigin}> $moves
	 * @param  ?string $by The id of the account that moved them.
	 * @throws RecordException When the redirects can't be written.
	 * @throws InvalidRoute When the compiled route table can't be written.
	 */
	public function moved(array $moves, ?string $by = null): void
	{
		$moves = array_values(array_filter(
			$moves,
			fn (array $move): bool => $move['from'] !== $move['to'] && $this->rows->isPath($move['from']) && $this->rows->isPath($move['to'])
		));

		if ($moves === []) {
			return;
		}

		$now = $this->clock->now();

		$this->rows->write(function () use ($moves, $now, $by): void {
			foreach ($moves as $move) {
				$this->rows->delete($move['to']);

				foreach ($this->rows->all() as $row) {
					if ($row->to === $move['from'] && $row->from !== $move['from']) {
						$this->rows->save($move['entry'] === null ? $row->leadingTo($move['to'], null) : $row->leadingTo(null, $move['entry']));
					}
				}

				$this->rows->save(new RedirectRow(
					$move['from'],
					$move['entry'] === null ? $move['to'] : null,
					$move['entry'],
					added: $now,
					by: $by,
					via: $move['via']
				));
			}
		});
	}
}
