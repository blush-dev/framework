<?php

/**
 * Account removal command.
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
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\InvalidInput;
use Blush\Console\Output;
use Blush\Console\Prompt;

/**
 * Deletes an account, after asking (or with `--yes`). Its sessions end at
 * their next request. Its author entry and content are left alone.
 */
#[Command('account:remove', 'Delete an admin account.')]
final readonly class RemoveAccount
{
	public function __construct(private Accounts $store)
	{}

	/**
	 * @throws AuthException When the account's record is damaged.
	 * @throws InvalidInput
	 */
	public function __invoke(
		Output $output,
		Prompt $prompt,
		#[Argument('The account\'s username.')] string $username,
		#[Option('Delete without asking.')] bool $yes = false
	): ExitCode {
		if ($this->store->find($username) === null) {
			$output->error(sprintf('There\'s no account named "%s".', $username));

			return ExitCode::Failure;
		}

		if (! $yes && ! $prompt->confirm(sprintf('Delete the "%s" account?', $username))) {
			$output->line('Nothing was deleted.');

			return ExitCode::Failure;
		}

		$this->store->delete($username);
		$output->success(sprintf('Deleted the "%s" account.', $username));

		return ExitCode::Success;
	}
}
