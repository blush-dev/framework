<?php

/**
 * Entries expired event.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Events;

use DateTimeImmutable;
use Blush\Content\Entry\Entry;

/**
 * Dispatched when entries expire (D-852): entries that were on the site
 * when the go-live job last looked and are expired now, their `expires`
 * date come, so a plugin can purge a CDN or tell someone. As with
 * `EntriesWentLive`, without cron the next runner catches up, so
 * `$since` (the job's last look) may be long before `$at`; each entry's
 * `expires` says when it expired.
 */
final readonly class EntriesExpired
{
	/**
	 * @param list<Entry> $entries
	 */
	public function __construct(
		public array $entries,
		public ?DateTimeImmutable $since,
		public DateTimeImmutable $at
	) {}
}
