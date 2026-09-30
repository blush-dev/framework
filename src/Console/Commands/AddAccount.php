<?php

/**
 * Account creation command.
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
use Blush\Auth\BuiltInRole;
use Blush\Console\Attributes\Argument;
use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\InvalidInput;
use Blush\Console\Output;
use Blush\Console\Prompt;

/**
 * Creates an admin account (D-217), asking for its password twice without
 * showing it. It needs a terminal, so a password never lands in shell
 * history. When the account's author has no entry yet, it offers to
 * create one, the account's public name and bio (D-259).
 */
#[Command('account:add', 'Create an admin account.')]
final readonly class AddAccount
{
	public function __construct(private Accounts $accounts)
	{}

	/**
	 * @param  list<string> $role
	 * @throws InvalidInput
	 */
	public function __invoke(
		Output $output,
		Prompt $prompt,
		#[Argument('The username (lowercase letters, digits, ".", "_", and "-").')] string $username,
		#[Option('A role for the account; repeat for more. Defaults to administrator.')] array $role = [],
		#[Option('The slug of the author entry the account writes as.')] ?string $author = null
	): ExitCode {
		$roles = $role === [] ? [BuiltInRole::Administrator->value] : $role;

		try {
			$this->accounts->checkRoles($roles);

			$password = $prompt->newSecret('Password:', 'Password again:', $this->accounts->passwordProblem(...));
			$account  = $this->accounts->create($username, $password, $roles, $author);
		} catch (AuthException $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		$output->success(sprintf('Created the "%s" account (%s).', $account->username, implode(', ', $account->roles)));

		return $account->author === null ? ExitCode::Success : AuthorPage::offer($output, $prompt, $this->accounts, $account->author);
	}
}
