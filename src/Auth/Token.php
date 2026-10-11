<?php

/**
 * Token.
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
 * An API token (D-744 to D-750): a way into the API for something that
 * isn't a person at a browser (a front end, a script, an agent), acting
 * as the account it belongs to. A record of the `tokens` table (`Tokens`),
 * kept on files in `storage/tokens/{id}.json`.
 *
 * - `id` is its record's, and the middle of the token's text, so a
 *   token finds its record without a scan.
 * - `account` is the id of the account it acts as.
 * - `name` says what it's for ("Astro front end").
 * - `hash` is a SHA-256 of its secret, which is shown once and never
 *   kept, as `PasswordLink` keeps its token.
 * - `capabilities` narrows what it may do to some of its account's
 *   capabilities, or is `null` for all of them. Either way it's checked
 *   against the account's roles as they are on each request.
 * - `created` and `expires` are Unix times; `expires` is `null` for a
 *   token that never does.
 * - `createdBy` is the id of the account that made it, or `''` for one
 *   made on the command line.
 * - `lastUsed` is when it was last used, written at most hourly
 *   (`Tokens::USE_INTERVAL`), or `null` for never.
 * - `client` is the OAuth client it was given to, for later (D-747).
 */
final readonly class Token
{
	/**
	 * The longest a name may be, in characters.
	 */
	public const int NAME_LENGTH = 100;

	/**
	 * @param  ?list<string> $capabilities
	 * @throws AuthException For an invalid id, account, name, or capability.
	 */
	public function __construct(
		public string $account,
		public string $name,
		public string $hash,
		public ?array $capabilities = null,
		public int $created = 0,
		public string $createdBy = '',
		public ?int $expires = null,
		public ?int $lastUsed = null,
		public ?string $client = null,
		public string $id = ''
	) {
		if (! Uuid::isValid($account)) {
			throw new AuthException(sprintf('"%s" isn\'t an account\'s id.', $account));
		}

		if ($id !== '' && ! Uuid::isValid($id)) {
			throw new AuthException(sprintf('"%s" isn\'t a token\'s id.', $id));
		}

		if (! self::isValidName($name)) {
			throw new AuthException(sprintf('A token\'s name is up to %d characters on one line.', self::NAME_LENGTH));
		}

		foreach ($capabilities ?? [] as $capability) {
			if (preg_match(Capabilities::NAME, $capability) !== 1) {
				throw new AuthException(sprintf('"%s" isn\'t a capability name.', $capability));
			}
		}
	}

	/**
	 * Whether a string can be a token's name: what an account's name may
	 * be (`Account::isValidName()`), up to the same length.
	 */
	public static function isValidName(string $name): bool
	{
		return Account::isValidName($name) && mb_strlen($name) <= self::NAME_LENGTH;
	}

	/**
	 * Whether the token has expired at a time.
	 */
	public function isExpired(int $now): bool
	{
		return $this->expires !== null && $this->expires <= $now;
	}

	/**
	 * Whether a secret is this token's.
	 */
	public function accepts(string $secret): bool
	{
		return hash_equals($this->hash, hash('sha256', $secret));
	}

	/**
	 * Returns a copy with its id, once it's saved.
	 *
	 * @throws AuthException For an invalid id.
	 */
	#[NoDiscard]
	public function withId(string $id): self
	{
		return new self($this->account, $this->name, $this->hash, $this->capabilities, $this->created, $this->createdBy, $this->expires, $this->lastUsed, $this->client, $id);
	}

	/**
	 * Returns a copy last used at a time.
	 */
	#[NoDiscard]
	public function withLastUsed(int $time): self
	{
		return clone($this, ['lastUsed' => $time]);
	}

	/**
	 * Builds a token from its stored array and its record's id.
	 *
	 * @param  array<mixed> $data
	 * @throws AuthException When the data isn't a token.
	 */
	public static function fromArray(array $data, string $id = ''): self
	{
		$capabilities = $data['capabilities'] ?? null;

		if (! is_string($data['account'] ?? null) || ! is_string($data['name'] ?? null) || ! is_string($data['hash'] ?? null)) {
			throw new AuthException('A token needs an "account", a "name", and a "hash".');
		}

		if ($capabilities !== null && (! is_array($capabilities) || ! array_is_list($capabilities) || ! array_all($capabilities, static fn (mixed $item): bool => is_string($item)))) {
			throw new AuthException('A token\'s "capabilities" are a list of names, or null.');
		}

		/** @var ?list<string> $capabilities */
		return new self(
			account: $data['account'],
			name: $data['name'],
			hash: $data['hash'],
			capabilities: $capabilities,
			created: is_int($data['created'] ?? null) ? $data['created'] : 0,
			createdBy: is_string($data['createdBy'] ?? null) ? $data['createdBy'] : '',
			expires: is_int($data['expires'] ?? null) ? $data['expires'] : null,
			lastUsed: is_int($data['lastUsed'] ?? null) ? $data['lastUsed'] : null,
			client: is_string($data['client'] ?? null) ? $data['client'] : null,
			id: $id
		);
	}

	/**
	 * Returns the token as its stored array, without its id, which is
	 * the record's.
	 *
	 * @return array{account: string, name: string, hash: string, capabilities: ?list<string>, created: int, createdBy: string, expires: ?int, lastUsed: ?int, client: ?string}
	 */
	public function toArray(): array
	{
		return [
			'account'      => $this->account,
			'name'         => $this->name,
			'hash'         => $this->hash,
			'capabilities' => $this->capabilities,
			'created'      => $this->created,
			'createdBy'    => $this->createdBy,
			'expires'      => $this->expires,
			'lastUsed'     => $this->lastUsed,
			'client'       => $this->client
		];
	}
}
