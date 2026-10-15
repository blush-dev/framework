<?php

/**
 * Core workflows.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Workflow;

use Override;
use Blush\Translation\Messages;

/**
 * The workflow core registers for entries (D-735): `standard`, of draft
 * and published, with Publish and Unpublish, which need `publish` alone
 * (D-734).
 */
final readonly class CoreWorkflows implements WorkflowSource
{
	public function __construct(private Messages $messages)
	{}

	/**
	 * Returns the standard workflow.
	 *
	 * @return list<Workflow>
	 */
	#[Override]
	public function workflows(): array
	{
		return [
			new Workflow(
				Workflow::STANDARD,
				$this->messages->t('workflow.workflow.standard', 'Standard'),
				[Status::DRAFT, Status::PUBLISHED],
				Status::DRAFT,
				[
					new Transition('publish', $this->messages->t('workflow.transition.publish', 'Publish'), [Status::DRAFT], Status::PUBLISHED, capability: false),
					new Transition('unpublish', $this->messages->t('workflow.transition.unpublish', 'Unpublish'), [Status::PUBLISHED], Status::DRAFT, capability: false)
				]
			)
		];
	}
}
