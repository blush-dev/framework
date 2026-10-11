<?php

/**
 * Reindex tool.
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
use Blush\Cache\ContentVersion;
use Blush\Content\Index\Indexer;
use Blush\Job\JobQueue;
use Blush\Job\JobType;
use Blush\Job\Jobs\MediaIndexJob;
use Blush\Media\Index\MediaIndexer;
use Blush\Tool\Tool;
use Blush\Tool\ToolAnnotations;
use Blush\Tool\ToolContext;
use Blush\Tool\ToolError;
use Blush\Tool\ToolResult;

/**
 * `core/reindex`: brings the content and media indexes up to date with
 * the files, in the background (D-621, D-761). A large media rebuild goes
 * on as its own job (D-626). It's for looking after the site, so apps
 * aren't offered it.
 */
final class Reindex extends Tool
{
	public function __construct(
		private readonly Indexer $indexer,
		private readonly MediaIndexer $media,
		private readonly ContentVersion $version,
		private readonly JobQueue $jobs
	) {}

	#[Override]
	public function title(): string
	{
		return 'Reindex Content';
	}

	#[Override]
	public function description(): string
	{
		return 'Update the content and media indexes from the files, without pulling or clearing other caches.';
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
		$report = $this->indexer->index();
		$media  = $this->media->index(limit: MediaIndexJob::BATCH);

		if ($media->pending > 0) {
			$this->jobs->push(JobType::MediaIndex->value, unique: JobType::MediaIndex->value);
		}

		if ($report->written) {
			$this->version->bump();
		}

		$summary = sprintf(
			'Indexed %d %s: %d added, %d changed, %d removed.',
			$report->total,
			$report->total === 1 ? 'entry' : 'entries',
			count($report->added),
			count($report->changed),
			count($report->removed)
		) . sprintf(' Indexed %d media %s.', $media->total, $media->total === 1 ? 'file' : 'files')
			. ($media->pending > 0 ? sprintf(' %d more are being read in the background.', $media->pending) : '');

		if ($report->failures !== []) {
			throw new ToolError(
				sprintf('%s %d file(s) couldn\'t be indexed.', $summary, count($report->failures)),
				array_map(static fn (string $path, string $message): string => "{$path}: {$message}", array_keys($report->failures), $report->failures)
			);
		}

		return ToolResult::done($summary);
	}
}
