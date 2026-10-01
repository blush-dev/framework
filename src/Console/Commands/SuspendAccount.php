<?php

/**
 * Suspend account command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Auth\AccountStore;
use Blush\Auth\Accounts;
use Blush\Auth\AuthException;
use Blush\Console\Attributes\Argument;
use Blush\Console\Attributes\Command;
use Blush\Console\ExitCode;
use Blush\Console\Output;

/**
 * Suspends an account: it's signed out at its next request, and can't
 * sign in or use a password link until it's reinstated (D-312). Nothing
 * else about it changes.
 */
#[Command('account:suspend', 'Suspend an admin account until it\'s reinstated.')]
final readonly class SuspendAccount
{
	public function __construct(
		private AccountStore $store,
		private Accounts $accounts
	) {}

	public function __invoke(
		Output $output,
		#[Argument('The account\'s username.')] string $username
	): ExitCode {
		try {
			$account = $this->store->find($username) ?? throw new AuthException(sprintf('There\'s no account named "%s".', $username));
		} catch (AuthException $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		if ($account->suspended) {
			$output->line(sprintf('"%s" was already suspended.', $username));

			return ExitCode::Success;
		}

		$this->accounts->setSuspended($account, true);
		$output->success(sprintf('"%s" is suspended and signed out.', $username));

		return ExitCode::Success;
	}
}
