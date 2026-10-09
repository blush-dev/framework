<?php

/**
 * Reinstate account command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Auth\Accounts;
use Blush\Auth\AuthException;
use Blush\Console\Attributes\Argument;
use Blush\Console\Attributes\Command;
use Blush\Console\ExitCode;
use Blush\Console\Output;

/**
 * Reinstates a suspended account (D-312), so it can sign in again.
 */
#[Command('account:reinstate', 'Reinstate a suspended admin account.')]
final readonly class ReinstateAccount
{
	public function __construct(
		private Accounts $accounts
	) {}

	public function __invoke(
		Output $output,
		#[Argument('The account\'s username.')] string $username
	): ExitCode {
		try {
			$account = $this->accounts->find($username) ?? throw new AuthException(sprintf('There\'s no account named "%s".', $username));
		} catch (AuthException $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		if (! $account->suspended) {
			$output->line(sprintf('"%s" wasn\'t suspended.', $username));

			return ExitCode::Success;
		}

		$this->accounts->setSuspended($account, false);
		$output->success(sprintf('"%s" is reinstated.', $username));

		return ExitCode::Success;
	}
}
