<?php

/**
 * Status source interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Workflow;

/**
 * Supplies statuses from core or an extension (D-735). A provider tags
 * its source with `StatusSource::TAG` in its `TAGS`. Core's come first
 * (`CoreStatuses`), and when two sources register a status by one
 * name, the first is kept (D-597).
 */
interface StatusSource
{
	/**
	 * The container tag for status sources.
	 */
	public const string TAG = 'workflow.statuses';

	/**
	 * Returns the statuses.
	 *
	 * @return iterable<Status>
	 */
	public function statuses(): iterable;
}
