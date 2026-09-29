<?php

/**
 * Account password command.
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
use Blush\Console\InvalidInput;
use Blush\Console\Output;
use Blush\Console\Prompt;

/**
 * Sets an account's password, which also signs it out everywhere.
 */
#[Command('account:password', 'Set an admin account\'s password.')]
final readonly class SetAccountPassword
{
	public function __construct(
		private AccountStore $store,
		private Accounts $accounts
	) {}

	/**
	 * @throws InvalidInput
	 */
	public function __invoke(
		Output $output,
		Prompt $prompt,
		#[Argument('The account\'s username.')] string $username
	): ExitCode {
		try {
			$account = $this->store->find($username) ?? throw new AuthException(sprintf('There\'s no account named "%s".', $username));

			$this->accounts->setPassword($account, $prompt->newSecret('New password:', 'New password again:', $this->accounts->passwordProblem(...)));
		} catch (AuthException $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		$output->success(sprintf('Set the password for "%s". Its sessions are signed out.', $username));

		return ExitCode::Success;
	}
}
