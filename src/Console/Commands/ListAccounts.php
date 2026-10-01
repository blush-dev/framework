<?php

/**
 * Account list command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use DateTimeImmutable;
use Blush\Auth\AccountStore;
use Blush\Auth\AuthException;
use Blush\Auth\Roles;
use Blush\Console\Attributes\Command;
use Blush\Console\ExitCode;
use Blush\Console\Output;
use Blush\Core\AppConfig;
use Blush\Core\Framework;

/**
 * Lists the admin accounts: username, name (D-322), roles, linked
 * author, status, and last sign-in. A role an account names that doesn't
 * exist is flagged.
 */
#[Command('account:list', 'List the admin accounts.')]
final readonly class ListAccounts
{
	public function __construct(
		private AccountStore $store,
		private Roles $roles,
		private AppConfig $app
	) {}

	/**
	 * @throws AuthException When an account's record is damaged.
	 */
	public function __invoke(Output $output): ExitCode
	{
		$accounts = $this->store->all();

		if ($accounts === []) {
			$output->line(sprintf('No accounts yet. Create one with: %s account:add <username>', Framework::BINARY));

			return ExitCode::Success;
		}

		$rows = [];

		foreach ($accounts as $account) {
			$rows[] = [
				$account->username,
				$account->name ?? '',
				implode(', ', array_map(fn (string $role): string => $this->roles->has($role) ? $role : "{$role} (unknown)", $account->roles)),
				$account->author ?? '',
				$account->status()->value,
				$account->lastLogin === null
					? 'never'
					: DateTimeImmutable::createFromTimestamp($account->lastLogin)->setTimezone($this->app->timezone())->format('Y-m-d H:i')
			];
		}

		$output->table(['Username', 'Name', 'Roles', 'Author', 'Status', 'Last sign-in'], $rows);

		return ExitCode::Success;
	}
}
