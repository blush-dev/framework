<?php

/**
 * Accounts.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

use Closure;
use Psr\Clock\ClockInterface;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordException;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordStore;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageArea;
use Blush\Storage\StorageException;
use Blush\Support\RequestMemo;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * The accounts repository (D-669): the `accounts` table, keyed by
 * username, on whichever driver keeps the accounts area (on files,
 * `storage/accounts/{username}.json`). It reads and saves accounts, and
 * creates and changes them, checking what goes into them: a free
 * username, a long enough password, roles that exist, and a profile no
 * other account has. The `account:*` commands, `init`, and the admin
 * use it; what needs an account's profile entry asks `AccountProfiles`.
 *
 * An account's id is its record's (D-668), and it's what everything
 * that links to an account holds. An account file without one has a
 * steady id made from its username until its next save writes it.
 *
 * The admin creates an account without a password (`invite()`, D-312):
 * it gets a one-time link instead, for its person to choose one with
 * (`usePasswordLink()`). Until then its password hash is of random
 * bytes nobody knows.
 *
 * A token-only account (`createTokenOnly()`, D-744) never signs in: its
 * password hash is of random bytes nobody knows, for good, it never
 * gets a password or a password link, and an email address is its
 * choice (D-750). It's used through its API tokens (`Tokens`).
 *
 * Accounts are read once a request (D-807): every lookup, by username or
 * id, comes from one reading of the table, and an account is one object
 * however it's found.
 */
final readonly class Accounts
{
	/**
	 * The table's name.
	 */
	public const string TABLE = 'accounts';

	public function __construct(
		private RecordStores|RecordStore $stores,
		private Passwords $passwords,
		private Roles $roles,
		private AuthConfig $config,
		private ClockInterface $clock,
		private Messages $messages,
		private RequestMemo $memo = new RequestMemo()
	) {}

	/**
	 * The accounts' table.
	 */
	public static function table(): Table
	{
		return new Table(self::TABLE, StorageArea::Accounts, key: 'username', fields: ['username']);
	}

	/**
	 * Returns an account, or `null` when there's none by that username.
	 *
	 * @throws AuthException When the account's record is damaged.
	 */
	public function find(string $username): ?Account
	{
		if (! Account::isValidUsername($username)) {
			return null;
		}

		return $this->index()['username'][$username] ?? null;
	}

	/**
	 * Returns an account by its id, or `null` when there's none.
	 *
	 * @throws AuthException When the account's record is damaged.
	 */
	public function findById(string $id): ?Account
	{
		if ($id === '') {
			return null;
		}

		return $this->index()['id'][strtolower($id)] ?? null;
	}

	/**
	 * Returns every account, sorted by username.
	 *
	 * @return list<Account>
	 * @throws AuthException When a record is damaged.
	 */
	public function all(): array
	{
		return $this->index()['all'];
	}

	/**
	 * Returns every account, sorted by username, and by username and id,
	 * read once a request.
	 *
	 * @return array{all: list<Account>, username: array<string, Account>, id: array<string, Account>}
	 * @throws AuthException When a record is damaged.
	 */
	private function index(): array
	{
		return $this->memo->remember('accounts|index', function (): array {
			$table    = self::table();
			$records  = $this->read('', fn (RecordStore $store): array => $store->select($table, new RecordQuery())->records);
			$accounts = array_map($this->account(...), $records);

			usort($accounts, static fn (Account $a, Account $b): int => strcmp($a->username, $b->username));

			$index = ['all' => $accounts, 'username' => [], 'id' => []];

			foreach ($accounts as $account) {
				$index['username'][$account->username] = $account;
				$index['id'][strtolower($account->id)] = $account;
			}

			return $index;
		});
	}

	/**
	 * Whether there are any accounts.
	 *
	 * @throws AuthException When the accounts can't be read.
	 */
	public function isEmpty(): bool
	{
		$table = self::table();

		return $this->read('', fn (RecordStore $store): int => $store->count($table, new RecordQuery())) === 0;
	}

	/**
	 * Saves an account, adding or replacing it, and returns it with its
	 * id: a new account gets one, and one without its id written gets
	 * its steady one written.
	 *
	 * @throws AuthException When it can't be saved.
	 */
	public function save(Account $account): Account
	{
		$table = self::table();

		try {
			$store  = $this->store();
			$record = ($account->id === '' ? null : $store->find($table, $account->id))
				?? $store->findByKey($table, $account->username)
				?? Record::create($this->clock->now());

			$saved = $store->save($table, $record->withFields($account->toArray()));
		} catch (RecordException | StorageException $e) {
			throw new AuthException((string) $this->messages->t('auth.accounts.not_saved', 'The account "{username}" couldn\'t be saved: {error}', username: $account->username, error: $e->getMessage()), previous: $e);
		} finally {
			$this->memo->forget();
		}

		return $account->withId($saved->id);
	}

	/**
	 * Deletes an account. A missing one is nothing to delete.
	 *
	 * @throws AuthException When it can't be deleted.
	 */
	public function delete(string $username): void
	{
		$table  = self::table();
		$record = Account::isValidUsername($username)
			? $this->read($username, fn (RecordStore $store): ?Record => $store->findByKey($table, $username))
			: null;

		if ($record === null) {
			return;
		}

		try {
			$this->store()->delete($table, $record->id);
		} catch (RecordException | StorageException $e) {
			throw new AuthException((string) $this->messages->t('auth.accounts.not_deleted', 'The account "{username}" couldn\'t be deleted: {error}', username: $username, error: $e->getMessage()), previous: $e);
		} finally {
			$this->memo->forget();
		}
	}

	/**
	 * Creates and saves an account.
	 *
	 * Every account needs an email address (D-370); `$email` is required,
	 * though it comes last so the other arguments keep their places.
	 * `$profile` is a profile entry's id (`AccountProfiles::prepare()`
	 * finds or makes it from a slug).
	 *
	 * @param  list<string> $roles
	 * @throws AuthException When the username is taken or invalid, the
	 *                       password is too short, a role doesn't exist,
	 *                       the profile is another account's, the name is
	 *                       too long, or the email address is missing,
	 *                       invalid, or another account's.
	 */
	public function create(string $username, string $password, array $roles, ?string $profile = null, ?string $name = null, string $email = ''): Account
	{
		$problem = $this->usernameProblem($username);

		if ($problem !== null) {
			throw new AuthException((string) $problem);
		}

		$email = $this->checkEmail($email, $username);

		$this->checkPassword($password);
		$this->checkRoles($roles);
		$this->checkProfile($profile, $username);

		$account = new Account(
			username: $username,
			passwordHash: $this->passwords->hash($password),
			roles: self::settle($roles),
			profile: $profile,
			created: $this->clock->now()->getTimestamp(),
			name: $this->name($name),
			email: $email
		);

		return $this->save($account);
	}

	/**
	 * Creates and saves a token-only account (D-744), which never signs in
	 * and is used through its API tokens, such as an agent's. An email
	 * address is optional (D-750).
	 *
	 * @param  list<string> $roles
	 * @throws AuthException When the username is taken or invalid, a role
	 *                       doesn't exist, the name is too long, or an
	 *                       email address given won't do.
	 */
	public function createTokenOnly(string $username, array $roles, ?string $name = null, ?string $email = null): Account
	{
		$problem = $this->usernameProblem($username);

		if ($problem !== null) {
			throw new AuthException((string) $problem);
		}

		$email = $email === null || trim($email) === '' ? null : $this->checkEmail($email, $username);

		$this->checkRoles($roles);

		return $this->save(new Account(
			username: $username,
			passwordHash: $this->passwords->hash(bin2hex(random_bytes(32))),
			roles: self::settle($roles),
			created: $this->clock->now()->getTimestamp(),
			name: $this->name($name),
			email: $email,
			tokenOnly: true
		));
	}

	/**
	 * Returns why a new account can't have a username: it isn't one, or
	 * it's taken; else `null`.
	 */
	public function usernameProblem(string $username): ?Text
	{
		return match (true) {
			! Account::isValidUsername($username) => $this->messages->t('auth.accounts.username_invalid', '"{username}" can\'t be a username; use lowercase letters, digits, ".", "_", and "-" (up to 64).', username: $username),
			$this->find($username) !== null      => $this->messages->t('auth.accounts.username_taken', 'There\'s already an account named "{username}".', username: $username),
			default                              => null
		};
	}

	/**
	 * Creates and saves an account with a link for choosing its password
	 * instead of one (D-312), and returns it with the link's token.
	 *
	 * @param  list<string> $roles
	 * @return array{Account, string}
	 * @throws AuthException When the username is taken or invalid, a role
	 *                       doesn't exist, the profile is another
	 *                       account's, the name is too long, or the email
	 *                       address won't do.
	 */
	public function invite(string $username, array $roles, ?string $profile = null, ?string $name = null, string $email = ''): array
	{
		return $this->issuePasswordLink($this->create($username, bin2hex(random_bytes(32)), $roles, $profile, $name, $email));
	}

	/**
	 * Gives an account a new link for choosing a password, replacing any
	 * it had, and returns it with the link's token. Its password keeps
	 * working until the link is used.
	 *
	 * @return array{Account, string}
	 * @throws AuthException For a token-only account, which never has a
	 *                       password.
	 */
	public function issuePasswordLink(Account $account): array
	{
		$this->refuseTokenOnly($account);

		[$link, $token] = PasswordLink::make($this->clock->now()->getTimestamp(), $this->config->passwordLinkLifetime);

		return [$this->save($account->withPasswordLink($link)), $token];
	}

	/**
	 * Sets an account's password with its link's token, which ends the
	 * link (and signs out the account's sessions, as any new password
	 * does).
	 *
	 * @throws AuthException When there's no such account or link, the link
	 *                       expired or was replaced, the account is
	 *                       suspended or token-only, or the password is
	 *                       too short.
	 */
	public function usePasswordLink(string $username, string $token, string $password): Account
	{
		$account = $this->find(strtolower(trim($username)));

		if ($account === null || $account->passwordLink === null || ! $account->passwordLink->accepts($token, $this->clock->now()->getTimestamp())) {
			throw new AuthException((string) $this->messages->t('auth.accounts.link_expired', 'This link has expired, was used, or was replaced. Ask an administrator for a new one.'));
		}

		if ($account->suspended) {
			throw new AuthException((string) $this->messages->t('auth.accounts.suspended', 'This account is suspended. Ask an administrator to reinstate it.'));
		}

		$this->refuseTokenOnly($account);
		$this->checkPassword($password);

		return $this->save($account->withPasswordHash($this->passwords->hash($password))->withPasswordLink(null));
	}

	/**
	 * Suspends an account, which signs out its sessions, or reinstates
	 * it.
	 */
	public function setSuspended(Account $account, bool $suspended): Account
	{
		return $this->save($account->withSuspended($suspended));
	}

	/**
	 * Sets an account's password, which signs out its sessions and ends
	 * any password link it has.
	 *
	 * @throws AuthException When the account is token-only, or the
	 *                       password is too short.
	 */
	public function setPassword(Account $account, string $password): Account
	{
		$this->refuseTokenOnly($account);
		$this->checkPassword($password);

		return $this->save($account->withPasswordHash($this->passwords->hash($password))->withPasswordLink(null));
	}

	/**
	 * Sets an account's roles; none is the member's (D-365).
	 *
	 * @param  list<string> $roles
	 * @throws AuthException When a role doesn't exist.
	 */
	public function setRoles(Account $account, array $roles): Account
	{
		$this->checkRoles($roles);

		return $this->save($account->withRoles(self::settle($roles)));
	}

	/**
	 * Links an account to a profile, by the profile entry's id, or
	 * unlinks it. A profile belongs to one account (D-356).
	 * `AccountProfiles::link()` links by slug, checking the profile.
	 *
	 * @throws AuthException For an invalid id, or a profile another
	 *                       account is linked to.
	 */
	public function setProfile(Account $account, ?string $profile): Account
	{
		$this->checkProfile($profile, $account->username, $account->profile);

		return $this->save($account->withProfile($profile));
	}

	/**
	 * Names an account, tidying the name as typed, or takes its name away
	 * (`null` or an empty name).
	 *
	 * @throws AuthException When the name is too long or has line breaks.
	 */
	public function setName(Account $account, ?string $name): Account
	{
		return $this->save($account->withName($this->name($name)));
	}

	/**
	 * Returns a name tidied as typed, or `null` for none.
	 *
	 * @throws AuthException When it's too long or has line breaks.
	 */
	private function name(?string $name): ?string
	{
		$name = $name === null ? null : Account::tidyName($name);

		if ($name !== null && ! Account::isValidName($name)) {
			throw new AuthException((string) $this->messages->t('auth.accounts.name_too_long', 'A name is up to {length} characters on one line.', length: Account::NAME_LENGTH));
		}

		return $name;
	}

	/**
	 * Gives an account another email address (D-370), or takes a
	 * token-only account's away (D-750).
	 *
	 * @throws AuthException When it's missing, invalid, or another
	 *                       account's.
	 */
	public function setEmail(Account $account, string $email): Account
	{
		return $this->save($account->withEmail($account->tokenOnly && trim($email) === '' ? null : $this->checkEmail($email, $account->username)));
	}

	/**
	 * Returns an email address, trimmed, once it's checked: present,
	 * valid, and no other account's (in any case).
	 *
	 * @throws AuthException When it isn't.
	 */
	public function checkEmail(string $email, ?string $username = null): string
	{
		$email = trim($email);

		if ($email === '') {
			throw new AuthException((string) $this->messages->t('auth.accounts.email_required', 'Every account needs an email address.'));
		}

		if (! Account::isValidEmail($email)) {
			throw new AuthException((string) $this->messages->t('auth.accounts.email_invalid', '"{email}" isn\'t an email address.', email: $email));
		}

		$other = array_find($this->all(), static fn (Account $account): bool => $account->username !== $username && $account->email !== null && mb_strtolower($account->email) === mb_strtolower($email));

		if ($other !== null) {
			throw new AuthException((string) $this->messages->t('auth.accounts.email_taken', '{name} already has that email address.', name: $other->name ?? $other->username));
		}

		return $email;
	}

	/**
	 * Sets an account's preferences (D-235).
	 */
	public function setPreferences(Account $account, Preferences $preferences): Account
	{
		return $this->save($account->withPreferences($preferences));
	}

	/**
	 * Returns the account linked to a profile, by the profile entry's
	 * id, other than one, or `null`.
	 *
	 * @throws AuthException When a record is damaged.
	 */
	public function linkedTo(string $profile, ?string $except = null): ?Account
	{
		return array_find($this->all(), static fn (Account $account): bool => $account->profile === $profile && $account->username !== $except);
	}

	/**
	 * Returns what's wrong with a password, or `null` when it's fine.
	 */
	public function passwordProblem(string $password): ?Text
	{
		return mb_strlen($password) < $this->config->minPasswordLength
			? $this->messages->t('auth.accounts.password_too_short', 'Passwords must be at least {count, plural, one {# character} other {# characters}}.', count: $this->config->minPasswordLength)
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
			throw new AuthException((string) $problem);
		}
	}

	/**
	 * Whether the site has an owner (D-500) that isn't suspended.
	 *
	 * @throws AuthException When an account's record is damaged.
	 */
	public function hasOwner(): bool
	{
		return array_any($this->all(), static fn (Account $account): bool => $account->isOwner() && ! $account->suspended);
	}

	/**
	 * Returns the roles an account holds (D-365): each once, and the
	 * member only when there's nothing else, so an account always has a
	 * role and the member is what having none means.
	 *
	 * @param  list<string> $roles
	 * @return list<string>
	 */
	public static function settle(array $roles): array
	{
		$member = BuiltInRole::Member->value;
		$others = array_values(array_unique(array_filter($roles, static fn (string $role): bool => $role !== $member)));

		return $others === [] ? [$member] : $others;
	}

	/**
	 * Checks that each role exists. None at all is the member's (D-365).
	 *
	 * @param  list<string> $roles
	 * @throws AuthException
	 */
	public function checkRoles(array $roles): void
	{
		foreach ($roles as $role) {
			if (! $this->roles->has($role)) {
				throw new AuthException((string) $this->messages->t('auth.accounts.no_role', 'There\'s no "{role}" role; the roles are: {roles}.', role: $role, roles: implode(', ', array_keys($this->roles->all()))));
			}
		}
	}

	/**
	 * Refuses a password for a token-only account, which never signs in.
	 *
	 * @throws AuthException
	 */
	private function refuseTokenOnly(Account $account): void
	{
		if ($account->tokenOnly) {
			throw new AuthException((string) $this->messages->t('auth.accounts.token_only', '{username} is token-only: it never signs in, so it has no password.', username: $account->username));
		}
	}

	/**
	 * Refuses a profile another account is linked to: a profile is one
	 * person's public side, so it belongs to one account (D-356).
	 *
	 * @throws AuthException
	 */
	private function checkProfile(?string $profile, string $username, ?string $current = null): void
	{
		if ($profile === null || $profile === $current) {
			return;
		}

		$other = $this->linkedTo($profile, $username);

		if ($other !== null) {
			throw new AuthException((string) $this->messages->t('auth.accounts.profile_taken', 'That profile is {name}\'s already; a profile belongs to one account.', name: $other->name ?? $other->username));
		}
	}

	/**
	 * Returns what reading the table gives.
	 *
	 * @template T
	 * @param  Closure(RecordStore): T $read
	 * @return T
	 * @throws AuthException When it can't be read.
	 */
	private function read(string $what, Closure $read): mixed
	{
		try {
			return $read($this->store());
		} catch (RecordException | StorageException $e) {
			throw new AuthException((string) ($what === ''
				? $this->messages->t('auth.accounts.unreadable', 'The accounts can\'t be read: {error}', error: $e->getMessage())
				: $this->messages->t('auth.accounts.account_unreadable', 'The account "{username}" can\'t be read: {error}', username: $what, error: $e->getMessage())), previous: $e);
		}
	}

	/**
	 * The store that keeps the table.
	 *
	 * @throws StorageException
	 */
	private function store(): RecordStore
	{
		return $this->stores instanceof RecordStores ? $this->stores->store(self::table()) : $this->stores;
	}

	/**
	 * Returns a record's account, built once a request.
	 *
	 * @throws AuthException When it isn't one.
	 */
	private function account(Record $record): Account
	{
		return $this->memo->remember("account|{$record->id}|{$record->version}", static fn (): Account => Account::fromArray($record->fields, $record->id));
	}
}
