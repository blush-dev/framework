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
use Blush\Publish\PublishInProgress;
use Blush\Publish\Publisher;

/**
 * Puts content changes live, as `publish` and the webhook do (D-131).
 */
final class PublishAction extends AdminAction
{
	public function __construct(private readonly Publisher $publisher)
	{}

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
	public function run(): ActionResult
	{
		try {
			$report = $this->publisher->publish();
		} catch (PublishInProgress $e) {
			return ActionResult::failure($e->getMessage());
		}

		if (! $report->isPublished()) {
			return ActionResult::failure('Publishing stopped: the pull failed.', array_values(array_filter(explode("\n", $report->pull->output ?? ''))));
		}

		$failures = $report->index->failures ?? [];
		$details  = array_map(static fn (string $path, string $message): string => "{$path}: {$message}", array_keys($failures), $failures);

		return $failures === []
			? ActionResult::success(sprintf('Published in %d ms.', $report->milliseconds))
			: ActionResult::failure(sprintf('Published, but %d file(s) couldn\'t be indexed.', count($failures)), $details);
	}
}
