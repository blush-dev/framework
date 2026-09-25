<?php

/**
 * Content indexed event.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Events;

use Blush\Content\Index\IndexReport;

/**
 * Dispatched after the indexer stores the index, with what changed. Caches
 * listen for it to invalidate pages (M6).
 */
final readonly class ContentIndexed
{
	public function __construct(public IndexReport $report)
	{}
}
