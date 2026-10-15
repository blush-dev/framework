<?php

/**
 * Review statuses fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Workflow;

use Override;
use Blush\Workflow\Status;
use Blush\Workflow\StatusSource;
use Blush\Workflow\Subject;
use Blush\Workflow\Tone;

/**
 * A plugin's statuses: one that isn't live, one that is, one for
 * responses, and two that clash (a core status's name, and one of
 * core's own).
 */
final readonly class ReviewStatuses implements StatusSource
{
	/**
	 * @return list<Status>
	 */
	#[Override]
	public function statuses(): array
	{
		return [
			new Status('in_review', 'In Review', tone: Tone::Warn),
			new Status('featured', 'Featured', live: true, tone: Tone::Accent),
			new Status('spam', 'Spam', active: false, subject: Subject::Responses),
			new Status(Status::DRAFT, 'Rough'),
			new Status(Status::TRASH, 'Bin')
		];
	}
}
