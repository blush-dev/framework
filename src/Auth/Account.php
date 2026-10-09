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
use Blush\Support\Uuid;

/**
 * Someone who signs in to the admin (D-216, D-217). Accounts aren't
 * content: they're the `accounts` table (`Accounts`, D-669), kept on
 * files in `storage/accounts`, outside git and `user/`.
 *
 * - `id` is the account's record id, what everything that links to an
 *   account holds (D-668); `''` only for an account not yet saved.
 * - `username` is lowercase letters, digits, `.`, `_`, and `-`. It's
 *   how people name the account, and never changes.
 * - `roles` names one or more roles; the account can do what any allows.
 * - `profile` optionally links the account to its profile, by the
 *   profile entry's id. Entries crediting that profile are the
 *   account's own.
 * - `created` and `lastLogin` are Unix timestamps.
 * - `preferences` are how the person likes the admin (D-235).
 * - `suspended` accounts can't sign in, and their sessions end (D-312).
 * - `passwordLink` is a one-time link for choosing a password (D-312).
 * - `name` is what the admin calls the person (D-322): any text, up to
 *   100 characters, on one line. Without one, the admin falls back to
 *   the profile's title, then the username.
 * - `email` is the person's email address (D-370). Every account needs
 *   one; it's `null` only for an account saved before emails, which the
 *   admin asks to be given one. It's for the people who manage accounts:
 *   Blush sends no email.
 */
final readonly class Account
{
	/**
	 * What a username may be.
	 */
	public const string USERNAME = '/^[a-z0-9][a-z0-9._-]{0,63}$/';

	/**
	 * The longest a name may be, in characters.
	 */
	public const int NAME_LENGTH = 100;

	/**
	 * The longest an email address may be, in characters.
	 */
	public const int EMAIL_LENGTH = 254;

	/**
	 * @param list<string> $roles
	 * @throws AuthException For an invalid username, profile id, id, name, or email.
	 */
	public function __construct(
		public string $username,
		public string $passwordHash,
		public array $roles = [],
		public ?string $profile = null,
		public int $created = 0,
		public ?int $lastLogin = null,
		public Preferences $preferences = new Preferences(),
		public bool $suspended = false,
		public ?PasswordLink $passwordLink = null,
		public ?string $name = null,
		public ?string $email = null,
		public string $id = ''
	) {
		if (! self::isValidUsername($username)) {
			throw new AuthException(sprintf('"%s" can\'t be a username; use lowercase letters, digits, ".", "_", and "-" (up to 64).', $username));
		}

		if ($profile !== null && ! Uuid::isValid($profile)) {
			throw new AuthException(sprintf('"%s" isn\'t a profile\'s id.', $profile));
		}

		if ($id !== '' && ! Uuid::isValid($id)) {
			throw new AuthException(sprintf('"%s" isn\'t an account\'s id.', $id));
		}

		if ($name !== null && ! self::isValidName($name)) {
			throw new AuthException(sprintf('A name is up to %d characters on one line.', self::NAME_LENGTH));
		}

		if ($email !== null && ! self::isValidEmail($email)) {
			throw new AuthException(sprintf('"%s" isn\'t an email address.', $email));
		}
	}

	/**
	 * Whether a string can be an email address: one `@` with something on
	 * either side, a dot in the domain, no spaces, and not too long.
	 */
	public static function isValidEmail(string $email): bool
	{
		return mb_strlen($email) <= self::EMAIL_LENGTH && filter_var($email, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE) !== false;
	}

	/**
	 * Whether a string can be a username.
	 */
	public static function isValidUsername(string $username): bool
	{
		return preg_match(self::USERNAME, $username) === 1;
	}

	/**
	 * Whether a string can be a name: not empty, no space at either end,
	 * no control characters or line breaks, and not too long.
	 */
	public static function isValidName(string $name): bool
	{
		return $name !== ''
			&& $name === trim($name)
			&& mb_check_encoding($name, 'UTF-8')
			&& preg_match('/[\p{Cc}\p{Zl}\p{Zp}]/u', $name) !== 1
			&& mb_strlen($name) <= self::NAME_LENGTH;
	}

	/**
	 * Tidies a name as typed: spaces and line breaks become single spaces,
	 * trimmed at either end. Returns `null` for an empty name.
	 */
	public static function tidyName(string $name): ?string
	{
		$name = trim(preg_replace('/[\s\p{Z}]+/u', ' ', $name) ?? $name);

		return $name === '' ? null : $name;
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
	 * Returns a copy with its id, once it's saved.
	 *
	 * @throws AuthException For an invalid id.
	 */
	#[NoDiscard]
	public function withId(string $id): self
	{
		return new self($this->username, $this->passwordHash, $this->roles, $this->profile, $this->created, $this->lastLogin, $this->preferences, $this->suspended, $this->passwordLink, $this->name, $this->email, $id);
	}

	/**
	 * Returns a copy linked to another profile, by its id, or none.
	 *
	 * @throws AuthException For an invalid id.
	 */
	#[NoDiscard]
	public function withProfile(?string $profile): self
	{
		return new self($this->username, $this->passwordHash, $this->roles, $profile, $this->created, $this->lastLogin, $this->preferences, $this->suspended, $this->passwordLink, $this->name, $this->email, $this->id);
	}

	/**
	 * Returns a copy with another name, or none.
	 *
	 * @throws AuthException For an invalid name.
	 */
	#[NoDiscard]
	public function withName(?string $name): self
	{
		return new self($this->username, $this->passwordHash, $this->roles, $this->profile, $this->created, $this->lastLogin, $this->preferences, $this->suspended, $this->passwordLink, $name, $this->email, $this->id);
	}

	/**
	 * Returns a copy with another email address.
	 *
	 * @throws AuthException For an invalid one.
	 */
	#[NoDiscard]
	public function withEmail(string $email): self
	{
		return new self($this->username, $this->passwordHash, $this->roles, $this->profile, $this->created, $this->lastLogin, $this->preferences, $this->suspended, $this->passwordLink, $this->name, $email, $this->id);
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
	 * Whether the account holds the owner role (D-500).
	 */
	public function isOwner(): bool
	{
		return in_array(BuiltInRole::Owner->value, $this->roles, true);
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
	 * Builds an account from its stored array and its record's id.
	 *
	 * @param  array<mixed> $data
	 * @throws AuthException When the data isn't an account.
	 */
	public static function fromArray(array $data, string $id = ''): self
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
			profile: is_string($data['profile'] ?? null) && Uuid::isValid($data['profile']) ? strtolower($data['profile']) : null,
			created: is_int($data['created'] ?? null) ? $data['created'] : 0,
			lastLogin: is_int($data['lastLogin'] ?? null) ? $data['lastLogin'] : null,
			preferences: Preferences::fromArray(is_array($data['preferences'] ?? null) ? $data['preferences'] : []),
			suspended: ($data['suspended'] ?? false) === true,
			passwordLink: is_array($data['passwordLink'] ?? null) ? PasswordLink::fromArray($data['passwordLink']) : null,
			name: is_string($data['name'] ?? null) ? self::tidyName($data['name']) : null,
			email: is_string($data['email'] ?? null) && $data['email'] !== '' ? $data['email'] : null,
			id: $id
		);
	}

	/**
	 * Returns the account as its stored array, without its id, which is
	 * the record's. Preferences at their
	 * defaults are left out, and so is `preferences` when all are;
	 * `name`, `email`, `suspended`, and `passwordLink` are written only
	 * when set.
	 *
	 * @return array{username: string, email?: string, name?: string, passwordHash: string, roles: list<string>, profile: ?string, created: int, lastLogin: ?int, preferences?: array<string, string|bool|list<string>>, suspended?: true, passwordLink?: array{hash: string, expires: int}}
	 */
	public function toArray(): array
	{
		$preferences = $this->preferences->changed();

		return [
			'username'     => $this->username,
			...($this->email === null ? [] : ['email' => $this->email]),
			...($this->name === null ? [] : ['name' => $this->name]),
			'passwordHash' => $this->passwordHash,
			'roles'        => $this->roles,
			'profile'      => $this->profile,
			'created'      => $this->created,
			'lastLogin'    => $this->lastLogin,
			...($preferences === [] ? [] : ['preferences' => $preferences]),
			...($this->suspended ? ['suspended' => true] : []),
			...($this->passwordLink === null ? [] : ['passwordLink' => $this->passwordLink->toArray()])
		];
	}
}
