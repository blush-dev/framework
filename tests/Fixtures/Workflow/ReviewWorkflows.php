<?php

/**
 * Review workflows fixture.
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
use Blush\Workflow\Transition;
use Blush\Workflow\Workflow;
use Blush\Workflow\WorkflowSource;

/**
 * A plugin's workflows: an editorial one through review, one naming a
 * status nothing registers, and one clashing with core's `standard`.
 */
final readonly class ReviewWorkflows implements WorkflowSource
{
	/**
	 * @return list<Workflow>
	 */
	#[Override]
	public function workflows(): array
	{
		return [
			new Workflow('editorial', 'Editorial', [Status::DRAFT, 'in_review', Status::PUBLISHED], Status::DRAFT, [
				new Transition('submit', 'Submit for Review', [Status::DRAFT], 'in_review'),
				new Transition('publish', 'Publish', ['in_review'], Status::PUBLISHED, capability: false),
				new Transition('reject', 'Send Back', ['in_review'], Status::DRAFT),
				new Transition('unpublish', 'Unpublish', [Status::PUBLISHED], Status::DRAFT, capability: false)
			]),
			new Workflow('broken', 'Broken', [Status::DRAFT, 'missing'], Status::DRAFT),
			new Workflow(Workflow::STANDARD, 'Not Standard', [Status::DRAFT], Status::DRAFT)
		];
	}
}
