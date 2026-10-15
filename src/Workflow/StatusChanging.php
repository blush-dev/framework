<?php

/**
 * Status changing event.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Workflow;

use Stringable;
use Blush\Activity\Actor;
use Blush\Content\Entry\Entry;
use Blush\Event\Stoppable;
use Blush\Event\StoppableEvent;

/**
 * Dispatched before an entry's status changes (D-735, D-851), by a
 * transition (`$transition`) or by Trash, Restore, or Undo (`null`),
 * once the change is allowed and before it's written. A listener may
 * `refuse()` it, saying why, and the change is refused with that reason
 * and nothing is written: a plugin that wants a reviewer's note before
 * Publish, say.
 *
 * `$from` and `$to` are the statuses as stored (`published`, never
 * `scheduled`).
 */
final class StatusChanging implements StoppableEvent
{
	use Stoppable;

	/**
	 * Why a listener refused the change, or `null`.
	 */
	public private(set) string|Stringable|null $refusal = null;

	public function __construct(
		public readonly Actor $actor,
		public readonly Entry $entry,
		public readonly string $from,
		public readonly string $to,
		public readonly ?Transition $transition
	) {}

	/**
	 * Refuses the change, saying why, which the person or tool is told;
	 * no later listener runs.
	 */
	public function refuse(string|Stringable $reason): void
	{
		$this->refusal = $reason;

		$this->stopPropagation();
	}

	/**
	 * Whether a listener refused it.
	 */
	public function isRefused(): bool
	{
		return $this->refusal !== null;
	}
}
