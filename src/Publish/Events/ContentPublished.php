<?php

/**
 * Content published event.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Publish\Events;

use Blush\Publish\PublishReport;

/**
 * Dispatched after a publish, with its report, whether it came from the
 * CLI, the webhook, or (later) the admin. Extensions listen for it to
 * warm caches, ping search engines, or notify someone.
 */
final readonly class ContentPublished
{
	public function __construct(public PublishReport $report)
	{}
}
