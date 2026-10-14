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
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * `core/publish`: puts content changes live, as `publish` and the webhook
 * do (D-131), in the background (D-621, D-761). It's for looking after
 * the site, so apps aren't offered it.
 */
final class Publish extends Tool
{
	public function __construct(
		private readonly Publisher $publisher,
		private readonly Messages $messages
	) {}

	#[Override]
	public function title(): Text
	{
		return $this->messages->t('tool.publish.title', 'Publish');
	}

	#[Override]
	public function description(): Text
	{
		return $this->messages->t('tool.publish.description', 'Put content changes live: pull (when set up), reindex, and clear the caches.');
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
			throw new ToolError((string) $this->messages->t('tool.publish.pull_failed', 'Publishing stopped: the pull failed.'), array_values(array_filter(explode("\n", $report->pull->output ?? ''))));
		}

		$failures = $report->index->failures ?? [];
		$pending  = $report->media->pending ?? 0;

		if ($failures !== []) {
			throw new ToolError(
				(string) $this->messages->t('tool.publish.unindexed', 'Published, but {count, plural, one {# file} other {# files}} couldn\'t be indexed.', count: count($failures)),
				array_map(static fn (string $path, string $message): string => "{$path}: {$message}", array_keys($failures), $failures)
			);
		}

		return ToolResult::done($pending > 0
			? $this->messages->t('tool.publish.published_more', 'Published in {ms} ms. {pending, plural, one {# more media file is} other {# more media files are}} being read in the background.', ms: $report->milliseconds, pending: $pending)
			: $this->messages->t('tool.publish.published', 'Published in {ms} ms.', ms: $report->milliseconds));
	}
}
