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

use Blush\Auth\AccountProfiles;
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
 * history. When `--author` names a profile with no entry yet, it makes
 * one, a draft with the public name asked for (D-259, D-668). Without a
 * `--role`, it's the owner while the site has none, else an administrator
 * (D-500).
 */
#[Command('account:add', 'Create an admin account.')]
final readonly class AddAccount
{
	public function __construct(
		private Accounts $accounts,
		private AccountProfiles $profiles
	) {}

	/**
	 * @param  list<string> $role
	 * @throws InvalidInput
	 */
	public function __invoke(
		Output $output,
		Prompt $prompt,
		#[Argument('The username (lowercase letters, digits, ".", "_", and "-").')] string $username,
		#[Option('A role for the account; repeat for more. Defaults to owner while the site has none, else administrator.')] array $role = [],
		#[Option('The slug of the profile the account writes as.')] ?string $author = null,
		#[Option('What the admin calls the person, quoted when it has spaces.')] ?string $name = null,
		#[Option('Their email address, which every account needs; asked for when left out.')] ?string $email = null
	): ExitCode {
		try {
			$roles = $role !== [] ? $role : [($this->accounts->hasOwner() ? BuiltInRole::Administrator : BuiltInRole::Owner)->value];

			$this->accounts->checkRoles($roles);

			$email = $this->accounts->checkEmail($email ?? $prompt->ask('Email address:', null, $this->emailProblem(...)));

			$password = $prompt->newSecret('Password:', 'Password again:', $this->accounts->passwordProblem(...));
			$title    = $this->profiles->wouldCreate($author) ? self::publicName($prompt, (string) $author, $name) : null;
			$account  = $this->accounts->create($username, $password, $roles, $this->profiles->prepare($author, $title, $username), $name, $email);
		} catch (AuthException $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		$output->success(sprintf('Created the "%s" account (%s).', $account->username, implode(', ', $account->roles)));

		return ExitCode::Success;
	}

	/**
	 * Asks for the public name of a profile about to be made, its title.
	 *
	 * @throws InvalidInput When the answer can't be read.
	 */
	public static function publicName(Prompt $prompt, string $slug, ?string $name = null): string
	{
		$default = $name ?? $slug;

		return trim($prompt->ask(sprintf('The "%s" profile doesn\'t exist yet, so it\'s made as a draft. Public name:', $slug), $default)) ?: $default;
	}

	/**
	 * Returns why an email address won't do, or `null`.
	 */
	private function emailProblem(string $email): ?string
	{
		try {
			$this->accounts->checkEmail($email);
		} catch (AuthException $e) {
			return $e->getMessage();
		}

		return null;
	}
}
