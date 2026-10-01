<?php

/**
 * Account name command.
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
 * Sets the name the admin calls an account by (D-322), or takes it away
 * when no name is given, so the admin falls back to the author page's
 * title, then the username.
 */
#[Command('account:name', 'Set an admin account\'s name, or remove it.')]
final readonly class SetAccountName
{
	public function __construct(
		private AccountStore $store,
		private Accounts $accounts
	) {}

	public function __invoke(
		Output $output,
		#[Argument('The account\'s username.')] string $username,
		#[Argument('The name, quoted when it has spaces; leave it out to remove it.')] ?string $name = null
	): ExitCode {
		try {
			$account = $this->store->find($username) ?? throw new AuthException(sprintf('There\'s no account named "%s".', $username));
			$account = $this->accounts->setName($account, $name);
		} catch (AuthException $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		$output->success($account->name === null
			? sprintf('"%s" has no name now; the admin calls it "%s".', $username, $this->accounts->displayName($account))
			: sprintf('"%s" is named "%s" now.', $username, $account->name));

		return ExitCode::Success;
	}
}
