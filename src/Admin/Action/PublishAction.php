<?php

/**
 * Publish action.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Action;

use Override;
use Blush\Auth\Capability;
use Blush\Job\JobType;

/**
 * Puts content changes live, as `publish` and the webhook do (D-131), as
 * a job (`blush/publish`, D-621), so it can take longer than a request.
 */
final class PublishAction extends AdminAction
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): string
	{
		return 'Publish';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function description(): string
	{
		return 'Put content changes live: pull (when set up), reindex, and clear the caches.';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function capability(): string
	{
		return Capability::SitePublish->value;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function job(): string
	{
		return JobType::Publish->value;
	}
}
