<?php

/**
 * Entries went live event.
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
 * Dispatched when scheduled entries go live (D-621, D-623): entries that
 * were scheduled when the go-live job last looked and are published now,
 * so a plugin can send a webhook, purge a CDN, or post to a social site.
 * The job runs every minute with cron; without cron, the next runner
 * catches up, so `$since` (the job's last look) may be long before
 * `$at`, and a listener that only wants fresh news can check each
 * entry's `published`.
 *
 * An entry published as it's saved (not scheduled first) isn't in it.
 */
final readonly class EntriesWentLive
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
