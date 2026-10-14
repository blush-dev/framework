<?php

/**
 * Prune tokens job.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job\Jobs;

use Override;
use Blush\Auth\Tokens;
use Blush\Job\Job;
use Blush\Job\JobRecord;
use Blush\Job\JobResult;
use Blush\Mcp\OAuth\OAuthClients;
use Blush\Mcp\OAuth\OAuthGrants;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Removes API tokens that expired over 30 days ago (`Tokens::prune()`,
 * D-748); until then, an expired token stays listed, so its account can
 * see why something stopped working. An app's tokens go once they expire,
 * with its expired codes and refresh tokens, and apps nobody approved
 * within a day (D-752).
 */
final class PruneTokensJob extends Job
{
	public function __construct(
		private readonly Tokens $tokens,
		private readonly OAuthClients $clients,
		private readonly OAuthGrants $grants,
		private readonly Messages $messages
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): Text
	{
		return $this->messages->t('job.prune_tokens_job.label', 'Remove long-expired API tokens');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function handle(JobRecord $job): JobResult
	{
		$pruned  = $this->tokens->prune();
		$secrets = $this->grants->prune();
		$clients = $this->clients->prune();

		return JobResult::done($this->messages->t('job.prune_tokens_job.removed', 'Removed {tokens, plural, one {# expired token} other {# expired tokens}}, {codes, plural, one {# expired code} other {# expired codes}} and refresh tokens, and {apps, plural, one {# app} other {# apps}} nobody connected.', tokens: $pruned, codes: $secrets, apps: $clients));
	}
}
