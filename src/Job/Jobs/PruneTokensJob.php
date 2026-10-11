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
		private readonly OAuthGrants $grants
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): string
	{
		return 'Remove long-expired API tokens';
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

		return JobResult::done(sprintf('Removed %d expired token%s, %d expired code%s and refresh tokens, and %d app%s nobody connected.', $pruned, $pruned === 1 ? '' : 's', $secrets, $secrets === 1 ? '' : 's', $clients, $clients === 1 ? '' : 's'));
	}
}
