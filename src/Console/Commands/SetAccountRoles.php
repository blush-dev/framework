<?php

/**
 * Account roles command.
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
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\InvalidInput;
use Blush\Console\Output;

/**
 * Replaces an account's roles.
 */
#[Command('account:roles', 'Set an admin account\'s roles.')]
final readonly class SetAccountRoles
{
	public function __construct(
		private AccountStore $store,
		private Accounts $accounts
	) {}

	/**
	 * @param  list<string> $role
	 * @throws InvalidInput
	 */
	public function __invoke(
		Output $output,
		#[Argument('The account\'s username.')] string $username,
		#[Option('A role for the account; repeat for more.')] array $role = []
	): ExitCode {
		if ($role === []) {
			throw new InvalidInput('Give at least one --role.');
		}

		try {
			$account = $this->store->find($username) ?? throw new AuthException(sprintf('There\'s no account named "%s".', $username));
			$account = $this->accounts->setRoles($account, $role);
		} catch (AuthException $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		$output->success(sprintf('"%s" now has: %s.', $username, implode(', ', $account->roles)));

		return ExitCode::Success;
	}
}
