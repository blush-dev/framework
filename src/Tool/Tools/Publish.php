<?php

/**
 * Publish tool.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tool\Tools;

use Override;
use Blush\Auth\Capability;
use Blush\Job\Jobs\MediaIndexJob;
use Blush\Publish\PublishInProgress;
use Blush\Publish\Publisher;
use Blush\Tool\Tool;
use Blush\Tool\ToolAnnotations;
use Blush\Tool\ToolContext;
use Blush\Tool\ToolError;
use Blush\Tool\ToolResult;

/**
 * `core/publish`: puts content changes live, as `publish` and the webhook
 * do (D-131), in the background (D-621, D-761). It's for looking after
 * the site, so apps aren't offered it.
 */
final class Publish extends Tool
{
	public function __construct(private readonly Publisher $publisher)
	{}

	#[Override]
	public function title(): string
	{
		return 'Publish';
	}

	#[Override]
	public function description(): string
	{
		return 'Put content changes live: pull (when set up), reindex, and clear the caches.';
	}

	#[Override]
	public function capability(): Capability
	{
		return Capability::SitePublish;
	}

	#[Override]
	public function annotations(): ToolAnnotations
	{
		return ToolAnnotations::writes(idempotent: true);
	}

	#[Override]
	public function mcp(): bool
	{
		return false;
	}

	#[Override]
	public function queued(): bool
	{
		return true;
	}

	#[Override]
	public function handle(array $input, ToolContext $context): ToolResult
	{
		try {
			$report = $this->publisher->publish(mediaLimit: MediaIndexJob::BATCH);
		} catch (PublishInProgress $e) {
			throw new ToolError($e->getMessage());
		}

		if (! $report->isPublished()) {
			throw new ToolError('Publishing stopped: the pull failed.', array_values(array_filter(explode("\n", $report->pull->output ?? ''))));
		}

		$failures = $report->index->failures ?? [];
		$more     = ($report->media->pending ?? 0) > 0 ? sprintf(' %d more media files are being read in the background.', $report->media?->pending) : '';

		if ($failures !== []) {
			throw new ToolError(
				sprintf('Published, but %d file(s) couldn\'t be indexed.', count($failures)),
				array_map(static fn (string $path, string $message): string => "{$path}: {$message}", array_keys($failures), $failures)
			);
		}

		return ToolResult::done(sprintf('Published in %d ms.', $report->milliseconds) . $more);
	}
}
