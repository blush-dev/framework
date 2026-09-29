<?php

/**
 * Account management.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

use Psr\Clock\ClockInterface;
use Blush\Content\ContentRepository;

/**
 * Creates and changes accounts, checking what goes into them: a free
 * username, a long enough password, and roles that exist. The `account:*`
 * commands and `init` use it, as the admin will.
 */
final readonly class Accounts
{
	public function __construct(
		private AccountStore $store,
		private Passwords $passwords,
		private Roles $roles,
		private AuthConfig $config,
		private ClockInterface $clock,
		private ContentRepository $content
	) {}

	/**
	 * Creates and saves an account.
	 *
	 * @param  list<string> $roles
	 * @throws AuthException When the username is taken or invalid, the
	 *                       password is too short, or a role doesn't exist.
	 */
	public function create(string $username, string $password, array $roles, ?string $author = null): Account
	{
		if (! Account::isValidUsername($username)) {
			throw new AuthException(sprintf('"%s" can\'t be a username; use lowercase letters, digits, ".", "_", and "-" (up to 64).', $username));
		}

		if ($this->store->find($username) !== null) {
			throw new AuthException(sprintf('There\'s already an account named "%s".', $username));
		}

		$this->checkPassword($password);
		$this->checkRoles($roles);

		$account = new Account(
			username: $username,
			passwordHash: $this->passwords->hash($password),
			roles: array_values(array_unique($roles)),
			author: $author,
			created: $this->clock->now()->getTimestamp()
		);

		$this->store->save($account);

		return $account;
	}

	/**
	 * Sets an account's password, which signs out its sessions.
	 *
	 * @throws AuthException When the password is too short.
	 */
	public function setPassword(Account $account, string $password): Account
	{
		$this->checkPassword($password);

		$account = $account->withPasswordHash($this->passwords->hash($password));
		$this->store->save($account);

		return $account;
	}

	/**
	 * Sets an account's roles.
	 *
	 * @param  list<string> $roles
	 * @throws AuthException When a role doesn't exist.
	 */
	public function setRoles(Account $account, array $roles): Account
	{
		$this->checkRoles($roles);

		$account = $account->withRoles($roles);
		$this->store->save($account);

		return $account;
	}

	/**
	 * Links an account to an author, or unlinks it.
	 *
	 * @throws AuthException For an invalid slug.
	 */
	public function setAuthor(Account $account, ?string $author): Account
	{
		$account = $account->withAuthor($author);
		$this->store->save($account);

		return $account;
	}

	/**
	 * Whether an author exists: it has an entry, or entries credit it (a
	 * virtual term). An account can be linked before either happens, so
	 * this is advice, not a rule.
	 */
	public function hasAuthor(string $author): bool
	{
		return $this->content->term($this->config->authorTaxonomy, $author) !== null;
	}

	/**
	 * Returns what's wrong with a password, or `null` when it's fine.
	 */
	public function passwordProblem(string $password): ?string
	{
		return mb_strlen($password) < $this->config->minPasswordLength
			? sprintf('Passwords must be at least %d characters.', $this->config->minPasswordLength)
			: null;
	}

	/**
	 * Checks that a password is long enough.
	 *
	 * @throws AuthException
	 */
	public function checkPassword(string $password): void
	{
		$problem = $this->passwordProblem($password);

		if ($problem !== null) {
			throw new AuthException($problem);
		}
	}

	/**
	 * Checks that there's at least one role and that each exists.
	 *
	 * @param  list<string> $roles
	 * @throws AuthException
	 */
	public function checkRoles(array $roles): void
	{
		if ($roles === []) {
			throw new AuthException('An account needs at least one role.');
		}

		foreach ($roles as $role) {
			if (! $this->roles->has($role)) {
				throw new AuthException(sprintf('There\'s no "%s" role; the roles are: %s.', $role, implode(', ', array_keys($this->roles->all()))));
			}
		}
	}
}
