<?php

/**
 * Core statuses.
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
 * The statuses core registers for entries (D-735): `draft` and
 * `published`. Core's own names (`trash`, `scheduled`) aren't
 * registered; `Statuses` lists them.
 */
final readonly class CoreStatuses implements StatusSource
{
	public function __construct(private Messages $messages)
	{}

	/**
	 * Returns draft and published.
	 *
	 * @return list<Status>
	 */
	#[Override]
	public function statuses(): array
	{
		return [
			new Status(
				Status::DRAFT,
				$this->messages->t('workflow.status.draft', 'Draft'),
				$this->messages->t('workflow.status.drafts', 'Drafts')
			),
			new Status(
				Status::PUBLISHED,
				$this->messages->t('workflow.status.published', 'Published'),
				$this->messages->t('workflow.status.published_plural', 'Published'),
				live: true,
				tone: Tone::Good
			)
		];
	}
}
