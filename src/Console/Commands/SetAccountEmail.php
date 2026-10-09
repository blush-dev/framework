<?php

/**
 * Account email command.
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
 * Sets an account's email address (D-370), which every account needs:
 * an account saved before emails has none until it's given one here or
 * in the admin.
 */
#[Command('account:email', 'Set an admin account\'s email address.')]
final readonly class SetAccountEmail
{
	public function __construct(
		private Accounts $accounts
	) {}

	public function __invoke(
		Output $output,
		#[Argument('The account\'s username.')] string $username,
		#[Argument('The email address.')] string $email
	): ExitCode {
		try {
			$account = $this->accounts->find($username) ?? throw new AuthException(sprintf('There\'s no account named "%s".', $username));
			$account = $this->accounts->setEmail($account, $email);
		} catch (AuthException $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		$output->success(sprintf('"%s"\'s email address is %s now.', $username, $account->email));

		return ExitCode::Success;
	}
}
