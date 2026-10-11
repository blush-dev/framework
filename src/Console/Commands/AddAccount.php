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

use DateTimeImmutable;
use Blush\Admin\AdminLinks;
use Blush\Auth\AccountProfiles;
use Blush\Auth\Accounts;
use Blush\Auth\AuthException;
use Blush\Auth\BuiltInRole;
use Blush\Auth\Role;
use Blush\Auth\Roles;
use Blush\Console\Attributes\Argument;
use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\InvalidInput;
use Blush\Console\Output;
use Blush\Console\Prompt;
use Blush\Console\Verbosity;
use Blush\Core\AppConfig;

/**
 * Creates an admin account (D-217). On a terminal it asks for what isn't
 * passed (D-720): the username, a name (optional), the email address,
 * its roles, the profile it writes as (none, one no account has, or a new
 * one at its username, a draft with the public name asked for; D-259,
 * D-668), and whether to set its password now, twice without showing it,
 * or make a one-time link to send instead (`--link`, D-312), which needs
 * no terminal, so a script can make accounts. Without a role, it's the
 * owner while the site has none, else an administrator (D-500).
 *
 * `--token-only` makes a token-only account instead (D-744), such as an
 * agent's: it never signs in, so it gets no password, link, or profile,
 * and an email address is optional (D-750). `token:add` makes its
 * tokens.
 */
#[Command('account:add', 'Create an admin account.')]
final readonly class AddAccount
{
	public function __construct(
		private Accounts $accounts,
		private AccountProfiles $profiles,
		private Roles $roles,
		private AdminLinks $links,
		private AppConfig $app
	) {}

	/**
	 * @param  list<string> $role
	 * @throws InvalidInput
	 */
	public function __invoke(
		Output $output,
		Prompt $prompt,
		#[Argument('The username (lowercase letters, digits, ".", "_", and "-"); asked for when it isn\'t given.')] ?string $username = null,
		#[Option('A role for the account; repeat for more. Defaults to owner while the site has none, else administrator.')] array $role = [],
		#[Option('The slug of the profile the account writes as.')] ?string $author = null,
		#[Option('What the admin calls the person, quoted when it has spaces.')] ?string $name = null,
		#[Option('Their email address, which every account needs; asked for when left out.')] ?string $email = null,
		#[Option('Make a one-time link for choosing the password, to send them, instead of asking for one.')] bool $link = false,
		#[Option('Make an account that never signs in and is used through its API tokens alone, such as an agent\'s.', name: 'token-only')] bool $tokenOnly = false
	): ExitCode {
		if ($tokenOnly) {
			return $this->tokenOnly($output, $prompt, $username, $role, $name, $email);
		}

		try {
			$username ??= $prompt->ask('Username', null, $this->accounts->usernameProblem(...), 'jane');
			$name     ??= trim($prompt->ask('Name', '', placeholder: 'What the admin calls them (optional)'));
			$email      = $this->accounts->checkEmail($email ?? $prompt->ask('Email address', null, $this->emailProblem(...), 'jane@example.com'));
			$roles      = $role !== [] ? $role : $this->askRoles($prompt);

			$this->accounts->checkRoles($roles);

			$author ??= $this->askProfile($prompt, $username);
			$author   = $author === '' ? null : $author;
			$name     = $name === '' ? null : $name;
			$title    = $this->profiles->wouldCreate($author) ? self::publicName($prompt, (string) $author, $name) : null;
			$link     = $link || ($prompt->interactive && $prompt->select('Password', ['now' => 'Set one now', 'link' => 'Make a one-time link to send them'], 'now') === 'link');
			$profile  = fn (): ?string => $this->profiles->prepare($author, $title, $username);

			[$account, $token] = $link
				? $this->accounts->invite($username, $roles, $profile(), $name, $email)
				: [$this->accounts->create($username, $prompt->newSecret('Password:', 'Password again:', $this->accounts->passwordProblem(...)), $roles, $profile(), $name, $email), null];
		} catch (AuthException $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		$writes = $this->profiles->entry($account);

		$output->success(sprintf(
			'Created "%s" (%s)%s.',
			$account->username,
			implode(', ', array_map(fn (string $role): string => $this->roles->get($role)->label ?? $role, $account->roles)),
			$writes === null ? '' : ", writing as {$writes->title}"
		));

		if ($token !== null) {
			$output->line($this->links->setPassword($account, $token), Verbosity::Quiet);
			$output->comment(sprintf(
				'Send them this link to choose a password. It works once, until %s.',
				DateTimeImmutable::createFromTimestamp($account->passwordLink->expires ?? time())->setTimezone($this->app->timezone())->format('Y-m-d H:i T')
			));
		}

		return ExitCode::Success;
	}

	/**
	 * Makes a token-only account, asking for what isn't passed but a
	 * profile and a password, which it never has.
	 *
	 * @param  list<string> $roles
	 * @throws InvalidInput
	 */
	private function tokenOnly(Output $output, Prompt $prompt, ?string $username, array $roles, ?string $name, ?string $email): ExitCode
	{
		try {
			$username ??= $prompt->ask('Username', null, $this->accounts->usernameProblem(...), 'agent');
			$name     ??= trim($prompt->ask('Name', '', placeholder: 'What the admin calls it (optional)'));
			$email    ??= trim($prompt->ask('Email address', '', placeholder: 'Optional for a token-only account'));
			$roles      = $roles !== [] ? $roles : $this->askRoles($prompt);

			$account = $this->accounts->createTokenOnly($username, $roles, $name === '' ? null : $name, $email === '' ? null : $email);
		} catch (AuthException $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		$output->success(sprintf(
			'Created the token-only account "%s" (%s).',
			$account->username,
			implode(', ', array_map(fn (string $role): string => $this->roles->get($role)->label ?? $role, $account->roles))
		));
		$output->comment(sprintf('Make its tokens with "token:add %s".', $account->username));

		return ExitCode::Success;
	}

	/**
	 * Asks for the account's roles, by label, with the usual one picked;
	 * picking none keeps it.
	 *
	 * @return list<string>
	 */
	private function askRoles(Prompt $prompt): array
	{
		$default = ($this->accounts->hasOwner() ? BuiltInRole::Administrator : BuiltInRole::Owner)->value;
		$labels  = array_map(static fn (Role $role): string => $role->label, $this->roles->all());

		return $prompt->multiselect('Roles', $labels, [$default]) ?: [$default];
	}

	/**
	 * Asks which profile the account writes as: none, one no account has
	 * (by title), or a new one at its username. Returns its slug, or `''`
	 * for none.
	 */
	private function askProfile(Prompt $prompt, string $username): string
	{
		$options = ['' => 'None'];

		foreach ($this->profiles->available() as $profile) {
			$options[$profile->key] = "{$profile->title} ({$profile->key})";
		}

		if ($this->profiles->wouldCreate($username)) {
			$options[$username] = "A new profile: {$username}";
		}

		return count($options) === 1 ? '' : $prompt->select('Writes as', $options, '');
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
