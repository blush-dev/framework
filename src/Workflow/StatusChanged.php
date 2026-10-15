<?php

/**
 * Status changed event.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Workflow;

use Blush\Activity\Actor;
use Blush\Content\Entry\Entry;

/**
 * Dispatched once an entry's status has changed (D-735, D-851), by a
 * transition (`$transition`) or by Trash, Restore, or Undo (`null`):
 * for a plugin that tells a reviewer an entry waits, or sends a webhook
 * when one goes live. `$entry` is as it's saved now.
 *
 * `$from` and `$to` are the statuses as stored (`published`, never
 * `scheduled`); an entry scheduled when it's saved goes live later, and
 * is in `EntriesWentLive` then.
 */
final readonly class StatusChanged
{
	public function __construct(
		public Actor $actor,
		public Entry $entry,
		public string $from,
		public string $to,
		public ?Transition $transition
	) {}
}
