<?php

/**
 * Reindex action.
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
 * Brings the content and media indexes up to date with the files, as a
 * job (`blush/reindex`, D-621), so it can take longer than a request.
 */
final class ReindexAction extends AdminAction
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): string
	{
		return 'Reindex content';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function description(): string
	{
		return 'Update the content and media indexes from the files, without pulling or clearing other caches.';
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
		return JobType::Reindex->value;
	}
}
