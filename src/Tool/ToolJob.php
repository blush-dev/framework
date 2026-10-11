<?php

/**
 * Tool job.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tool;

use Override;
use Blush\Activity\Actor;
use Blush\Activity\Via;
use Blush\Auth\Accounts;
use Blush\Job\Job;
use Blush\Job\JobRecord;
use Blush\Job\JobResult;

/**
 * `core/tool`: runs a `queued()` tool in the background (D-761), with the
 * input it was given, for the account that asked and the way it came. It
 * isn't retried, since a tool isn't promised to be safe to run twice; a
 * tool that failed says why.
 */
final class ToolJob extends Job
{
	public function __construct(
		private readonly Tools $tools,
		private readonly ToolContexts $contexts,
		private readonly Accounts $accounts
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): string
	{
		return 'Run a tool';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function describe(JobRecord $job): string
	{
		$key = $job->data['tool'] ?? null;

		return (is_string($key) ? $this->tools->get($key)?->title() : null) ?? $this->label();
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function attempts(): int
	{
		return 1;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function handle(JobRecord $job): JobResult
	{
		$key     = $job->data['tool'] ?? null;
		$input   = $job->data['input'] ?? [];
		$account = $job->account === null ? null : $this->accounts->findById($job->account);

		if (! is_string($key) || ! is_array($input) || ($input !== [] && array_is_list($input))) {
			return JobResult::failed('The job doesn\'t say which tool to run, or with what.');
		}

		if ($account === null || $account->suspended) {
			return JobResult::failed('The account that asked for it is gone or suspended.');
		}

		$client = $job->data['client'] ?? null;
		$actor  = new Actor($account, Via::tryFrom(is_string($job->data['via'] ?? null) ? $job->data['via'] : '') ?? Via::Admin, is_string($client) ? $client : null);

		try {
			/** @var array<string, mixed> $input */
			$result = $this->tools->run($key, $input, $this->contexts->forActor($actor), wait: true);
		} catch (ToolError $error) {
			return JobResult::failed($error->getMessage(), $error->details);
		}

		return JobResult::done($result->text ?? 'Done.', $result->details, $result->data);
	}
}
