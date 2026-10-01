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
use Blush\Content\Type\ContentTypes;
use Blush\Content\Writer\ContentWriter;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\WriteException;

/**
 * Creates and changes accounts, checking what goes into them: a free
 * username, a long enough password, and roles that exist. The `account:*`
 * commands, `init`, and the admin use it.
 *
 * The admin creates an account without a password (`invite()`, D-312):
 * it gets a one-time link instead, for its person to choose one with
 * (`usePasswordLink()`). Until then its password hash is of random
 * bytes nobody knows.
 */
final readonly class Accounts
{
	public function __construct(
		private AccountStore $store,
		private Passwords $passwords,
		private Roles $roles,
		private AuthConfig $config,
		private ClockInterface $clock,
		private ContentRepository $content,
		private ContentTypes $types,
		private ContentWriter $writer
	) {}

	/**
	 * Creates and saves an account.
	 *
	 * @param  list<string> $roles
	 * @throws AuthException When the username is taken or invalid, the
	 *                       password is too short, a role doesn't exist,
	 *                       or the name is too long.
	 */
	public function create(string $username, string $password, array $roles, ?string $author = null, ?string $name = null): Account
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
			created: $this->clock->now()->getTimestamp(),
			name: $name === null ? null : Account::tidyName($name)
		);

		$this->store->save($account);

		return $account;
	}

	/**
	 * Creates and saves an account with a link for choosing its password
	 * instead of one (D-312), and returns it with the link's token.
	 *
	 * @param  list<string> $roles
	 * @return array{Account, string}
	 * @throws AuthException When the username is taken or invalid, a role
	 *                       doesn't exist, or the name is too long.
	 */
	public function invite(string $username, array $roles, ?string $author = null, ?string $name = null): array
	{
		return $this->issuePasswordLink($this->create($username, bin2hex(random_bytes(32)), $roles, $author, $name));
	}

	/**
	 * Gives an account a new link for choosing a password, replacing any
	 * it had, and returns it with the link's token. Its password keeps
	 * working until the link is used.
	 *
	 * @return array{Account, string}
	 */
	public function issuePasswordLink(Account $account): array
	{
		[$link, $token] = PasswordLink::make($this->clock->now()->getTimestamp(), $this->config->passwordLinkLifetime);

		$account = $account->withPasswordLink($link);
		$this->store->save($account);

		return [$account, $token];
	}

	/**
	 * Sets an account's password with its link's token, which ends the
	 * link (and signs out the account's sessions, as any new password
	 * does).
	 *
	 * @throws AuthException When there's no such account or link, the link
	 *                       expired or was replaced, the account is
	 *                       suspended, or the password is too short.
	 */
	public function usePasswordLink(string $username, string $token, string $password): Account
	{
		$account = $this->store->find(strtolower(trim($username)));

		if ($account === null || $account->passwordLink === null || ! $account->passwordLink->accepts($token, $this->clock->now()->getTimestamp())) {
			throw new AuthException('This link has expired, was used, or was replaced. Ask an administrator for a new one.');
		}

		if ($account->suspended) {
			throw new AuthException('This account is suspended. Ask an administrator to reinstate it.');
		}

		$this->checkPassword($password);

		$account = $account->withPasswordHash($this->passwords->hash($password))->withPasswordLink(null);
		$this->store->save($account);

		return $account;
	}

	/**
	 * Suspends an account, which signs out its sessions, or reinstates
	 * it.
	 */
	public function setSuspended(Account $account, bool $suspended): Account
	{
		$account = $account->withSuspended($suspended);
		$this->store->save($account);

		return $account;
	}

	/**
	 * Sets an account's password, which signs out its sessions and ends
	 * any password link it has.
	 *
	 * @throws AuthException When the password is too short.
	 */
	public function setPassword(Account $account, string $password): Account
	{
		$this->checkPassword($password);

		$account = $account->withPasswordHash($this->passwords->hash($password))->withPasswordLink(null);
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
	 * Names an account, tidying the name as typed, or takes its name away
	 * (`null` or an empty name).
	 *
	 * @throws AuthException When the name is too long or has line breaks.
	 */
	public function setName(Account $account, ?string $name): Account
	{
		$account = $account->withName($name === null ? null : Account::tidyName($name));
		$this->store->save($account);

		return $account;
	}

	/**
	 * Returns what the admin calls an account (D-322): its name, else its
	 * author page's title, else its username.
	 */
	public function displayName(Account $account): string
	{
		if ($account->name !== null) {
			return $account->name;
		}

		$title = $account->author === null ? '' : ($this->content->named($this->config->authorTaxonomy, $account->author)->title ?? '');

		return $title !== '' ? $title : $account->username;
	}

	/**
	 * Sets an account's preferences (D-235).
	 */
	public function setPreferences(Account $account, Preferences $preferences): Account
	{
		$account = $account->withPreferences($preferences);
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
	 * Whether an author has an entry of its own: the account's public
	 * name and bio (D-259), not just a virtual term.
	 */
	public function hasAuthorPage(string $author): bool
	{
		return $this->content->named($this->config->authorTaxonomy, $author) !== null;
	}

	/**
	 * Creates an author's entry, published, with its public name, and
	 * returns its ID (its path under `user/content`).
	 *
	 * @throws AuthException When the site has no author type, or the file
	 *                       exists or can't be written.
	 */
	public function createAuthorPage(string $author, string $name): string
	{
		$type = $this->types->find($this->config->authorTaxonomy)
			?? throw new AuthException(sprintf('The site has no "%s" content type for authors.', $this->config->authorTaxonomy));

		try {
			return $this->writer->create($type, $author, new EntryChanges(set: ['title' => $name], body: "\n"), $this->clock->now())->id;
		} catch (WriteException $e) {
			throw new AuthException($e->getMessage(), previous: $e);
		}
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
