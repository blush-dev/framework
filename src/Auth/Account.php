<?php

/**
 * Account.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

use NoDiscard;

/**
 * Someone who signs in to the admin (D-216, D-217). Accounts aren't
 * content: they live in `storage/accounts`, outside git and `user/`.
 *
 * - `username` is lowercase letters, digits, `.`, `_`, and `-`.
 * - `roles` names one or more roles; the account can do what any allows.
 * - `author` optionally links the account to an entry of the `author`
 *   type by slug. Entries crediting that author are the account's own.
 * - `created` and `lastLogin` are Unix timestamps.
 * - `preferences` are how the person likes the admin (D-235).
 * - `suspended` accounts can't sign in, and their sessions end (D-312).
 * - `passwordLink` is a one-time link for choosing a password (D-312).
 */
final readonly class Account
{
	/**
	 * What a username may be.
	 */
	public const string USERNAME = '/^[a-z0-9][a-z0-9._-]{0,63}$/';

	/**
	 * @param list<string> $roles
	 * @throws AuthException For an invalid username or author slug.
	 */
	public function __construct(
		public string $username,
		public string $passwordHash,
		public array $roles = [],
		public ?string $author = null,
		public int $created = 0,
		public ?int $lastLogin = null,
		public Preferences $preferences = new Preferences(),
		public bool $suspended = false,
		public ?PasswordLink $passwordLink = null
	) {
		if (! self::isValidUsername($username)) {
			throw new AuthException(sprintf('"%s" can\'t be a username; use lowercase letters, digits, ".", "_", and "-" (up to 64).', $username));
		}

		if ($author !== null && preg_match('/^[\p{Ll}\p{Lo}\p{N}][\p{Ll}\p{Lo}\p{N}_-]*$/u', $author) !== 1) {
			throw new AuthException(sprintf('"%s" isn\'t an author slug.', $author));
		}
	}

	/**
	 * Whether a string can be a username.
	 */
	public static function isValidUsername(string $username): bool
	{
		return preg_match(self::USERNAME, $username) === 1;
	}

	/**
	 * Returns a copy with a new password hash.
	 */
	#[NoDiscard]
	public function withPasswordHash(string $hash): self
	{
		return clone($this, ['passwordHash' => $hash]);
	}

	/**
	 * Returns a copy with other roles.
	 *
	 * @param list<string> $roles
	 */
	#[NoDiscard]
	public function withRoles(array $roles): self
	{
		return clone($this, ['roles' => array_values(array_unique($roles))]);
	}

	/**
	 * Returns a copy linked to another author, or none.
	 */
	#[NoDiscard]
	public function withAuthor(?string $author): self
	{
		return new self($this->username, $this->passwordHash, $this->roles, $author, $this->created, $this->lastLogin, $this->preferences, $this->suspended, $this->passwordLink);
	}

	/**
	 * Returns a copy with other preferences.
	 */
	#[NoDiscard]
	public function withPreferences(Preferences $preferences): self
	{
		return clone($this, ['preferences' => $preferences]);
	}

	/**
	 * Returns a copy suspended, or not.
	 */
	#[NoDiscard]
	public function withSuspended(bool $suspended): self
	{
		return clone($this, ['suspended' => $suspended]);
	}

	/**
	 * Returns a copy with a password link, or none.
	 */
	#[NoDiscard]
	public function withPasswordLink(?PasswordLink $link): self
	{
		return clone($this, ['passwordLink' => $link]);
	}

	/**
	 * Returns where the account stands.
	 */
	public function status(): AccountStatus
	{
		return match (true) {
			$this->suspended                                          => AccountStatus::Suspended,
			$this->lastLogin === null && $this->passwordLink !== null => AccountStatus::Invited,
			default                                                   => AccountStatus::Active
		};
	}

	/**
	 * Returns a copy that last signed in at a time.
	 */
	#[NoDiscard]
	public function withLastLogin(int $time): self
	{
		return clone($this, ['lastLogin' => $time]);
	}

	/**
	 * Builds an account from its stored array.
	 *
	 * @param  array<mixed> $data
	 * @throws AuthException When the data isn't an account.
	 */
	public static function fromArray(array $data): self
	{
		$roles = $data['roles'] ?? [];

		if (! is_string($data['username'] ?? null) || ! is_string($data['passwordHash'] ?? null) || ! is_array($roles) || ! array_is_list($roles) || ! array_all($roles, static fn (mixed $role): bool => is_string($role))) {
			throw new AuthException('An account needs a "username", a "passwordHash", and a list of "roles".');
		}

		/** @var list<string> $roles */
		return new self(
			username: $data['username'],
			passwordHash: $data['passwordHash'],
			roles: $roles,
			author: is_string($data['author'] ?? null) ? $data['author'] : null,
			created: is_int($data['created'] ?? null) ? $data['created'] : 0,
			lastLogin: is_int($data['lastLogin'] ?? null) ? $data['lastLogin'] : null,
			preferences: Preferences::fromArray(is_array($data['preferences'] ?? null) ? $data['preferences'] : []),
			suspended: ($data['suspended'] ?? false) === true,
			passwordLink: is_array($data['passwordLink'] ?? null) ? PasswordLink::fromArray($data['passwordLink']) : null
		);
	}

	/**
	 * Returns the account as its stored array. Preferences at their
	 * defaults are left out, and so is `preferences` when all are;
	 * `suspended` and `passwordLink` are written only when set.
	 *
	 * @return array{username: string, passwordHash: string, roles: list<string>, author: ?string, created: int, lastLogin: ?int, preferences?: array<string, string>, suspended?: true, passwordLink?: array{hash: string, expires: int}}
	 */
	public function toArray(): array
	{
		$preferences = $this->preferences->changed();

		return [
			'username'     => $this->username,
			'passwordHash' => $this->passwordHash,
			'roles'        => $this->roles,
			'author'       => $this->author,
			'created'      => $this->created,
			'lastLogin'    => $this->lastLogin,
			...($preferences === [] ? [] : ['preferences' => $preferences]),
			...($this->suspended ? ['suspended' => true] : []),
			...($this->passwordLink === null ? [] : ['passwordLink' => $this->passwordLink->toArray()])
		];
	}
}
