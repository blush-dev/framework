<?php

/**
 * Tokens.
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
use Blush\Core\Framework;
use Blush\Storage\Record\Order;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordException;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordStore;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageArea;
use Blush\Storage\StorageException;
use Blush\Support\Uuid;

/**
 * The API tokens repository (D-747): the `tokens` table of the accounts
 * area, known by id, on whichever driver keeps the area (on files,
 * `storage/tokens/{id}.json`). It makes tokens, finds the one a request
 * names, and revokes them, which deletes their records (D-748).
 *
 * A token's text is `blush_{id}_{secret}`: the binary's name, so secret
 * scanners can know a leaked one; the record's id; and 32 random bytes
 * as hex, shown once when it's made. Only a SHA-256 of the secret is
 * kept.
 */
final readonly class Tokens
{
	/**
	 * The table's name.
	 */
	public const string TABLE = 'tokens';

	/**
	 * The lifetimes a token may be given, in days (D-748); `null` is
	 * never expiring.
	 *
	 * @var list<int>
	 */
	public const array LIFETIMES = [30, 90, 365];

	/**
	 * The lifetime a token gets unless it's given another, in days.
	 */
	public const int DEFAULT_LIFETIME = 90;

	/**
	 * How long, in seconds, before a token's use is written again, so
	 * files aren't written on every request.
	 */
	public const int USE_INTERVAL = 3600;

	/**
	 * How long, in seconds, an expired token is kept before the pruning
	 * job removes it (D-748).
	 */
	public const int KEEP_EXPIRED = 30 * 86400;

	public function __construct(
		private RecordStores|RecordStore $stores,
		private ClockInterface $clock
	) {}

	/**
	 * The tokens' table.
	 */
	public static function table(): Table
	{
		return new Table(self::TABLE, StorageArea::Accounts, fields: ['account', 'expires']);
	}

	/**
	 * What a token's text starts with.
	 */
	public static function prefix(): string
	{
		return Framework::BINARY . '_';
	}

	/**
	 * Returns a token's id and secret from its text, or `null` when the
	 * text isn't a token's.
	 *
	 * @return ?array{string, string}
	 */
	public static function parse(string $text): ?array
	{
		$prefix = preg_quote(self::prefix(), '/');

		if (preg_match('/\A' . $prefix . '([0-9a-f-]{36})_([0-9a-f]{64})\z/', trim($text), $matches) !== 1 || ! Uuid::isValid($matches[1])) {
			return null;
		}

		return [$matches[1], $matches[2]];
	}

	/**
	 * Makes and saves a token for an account, and returns it with its
	 * text, which is shown once.
	 *
	 * @param  ?list<string> $capabilities Some of the account's capabilities, or `null` for all.
	 * @param  ?int          $days         How many days it lasts, one of `LIFETIMES`, or `null` for always.
	 * @return array{Token, string}
	 * @throws AuthException For an account that isn't saved, a name or capability that won't do, a lifetime that isn't one of `LIFETIMES`, or when it can't be saved.
	 */
	public function make(Account $account, string $name, ?array $capabilities = null, ?int $days = self::DEFAULT_LIFETIME, string $createdBy = ''): array
	{
		if ($account->id === '') {
			throw new AuthException(sprintf('The account "%s" isn\'t saved yet.', $account->username));
		}

		if ($days !== null && ! in_array($days, self::LIFETIMES, true)) {
			throw new AuthException(sprintf('A token lasts %s days, or never.', implode(', ', self::LIFETIMES)));
		}

		$name = Account::tidyName($name) ?? '';

		if ($name === '') {
			throw new AuthException('A token needs a name that says what it\'s for.');
		}

		$now    = $this->clock->now();
		$secret = bin2hex(random_bytes(32));
		$record = Record::create($now);
		$token  = new Token(
			account: $account->id,
			name: $name,
			hash: hash('sha256', $secret),
			capabilities: $capabilities === null ? null : array_values(array_unique($capabilities)),
			created: $now->getTimestamp(),
			createdBy: $createdBy,
			expires: $days === null ? null : $now->getTimestamp() + $days * 86400,
			id: $record->id
		);

		$this->save($token);

		return [$token, self::prefix() . "{$token->id}_{$secret}"];
	}

	/**
	 * Hands an OAuth client a token for an account (D-752): narrowed to
	 * capabilities, lasting some seconds, named for the client, and
	 * returns it with its text, which the client keeps. Its `client` is
	 * the client's record's id, so it works only on the MCP server.
	 *
	 * @param  ?list<string> $capabilities
	 * @return array{Token, string}
	 * @throws AuthException
	 */
	public function issue(Account $account, string $client, string $name, ?array $capabilities, int $lifetime): array
	{
		$now    = $this->clock->now();
		$secret = bin2hex(random_bytes(32));
		$record = Record::create($now);
		$token  = new Token(
			account: $account->id,
			name: Account::tidyName($name) ?? 'An app',
			hash: hash('sha256', $secret),
			capabilities: $capabilities,
			created: $now->getTimestamp(),
			createdBy: $account->id,
			expires: $now->getTimestamp() + $lifetime,
			client: $client,
			id: $record->id
		);

		$this->save($token);

		return [$token, self::prefix() . "{$token->id}_{$secret}"];
	}

	/**
	 * Revokes an account's tokens for an OAuth client, and returns how
	 * many there were.
	 *
	 * @throws AuthException
	 */
	public function revokeClient(Account|string $account, string $client): int
	{
		$id     = $account instanceof Account ? $account->id : $account;
		$query  = new RecordQuery()->where('account', '=', $id);
		$tokens = array_filter(
			array_map(self::token(...), $this->read(fn (RecordStore $store): array => $store->select(self::table(), $query)->records)),
			static fn (Token $token): bool => $token->client === $client
		);

		foreach ($tokens as $token) {
			$this->revoke($token);
		}

		return count($tokens);
	}

	/**
	 * Returns the token a text names, when its secret is right, or
	 * `null`. Whether it has expired, and whether its account may still
	 * use it, are for the caller.
	 *
	 * @throws AuthException When the tokens can't be read.
	 */
	public function verify(string $text): ?Token
	{
		[$id, $secret] = self::parse($text) ?? [null, null];

		if ($id === null || $secret === null) {
			return null;
		}

		$token = $this->find($id);

		return $token !== null && $token->accepts($secret) ? $token : null;
	}

	/**
	 * Returns a token by id, or `null`.
	 *
	 * @throws AuthException When the tokens can't be read.
	 */
	public function find(string $id): ?Token
	{
		if (! Uuid::isValid($id)) {
			return null;
		}

		$record = $this->read(fn (RecordStore $store): ?Record => $store->find(self::table(), $id));

		return $record === null ? null : self::token($record);
	}

	/**
	 * Returns an account's tokens, newest first.
	 *
	 * @return list<Token>
	 * @throws AuthException When the tokens can't be read.
	 */
	public function forAccount(Account $account): array
	{
		if ($account->id === '') {
			return [];
		}

		$query = new RecordQuery()->where('account', '=', $account->id)->orderBy('created', Order::Desc);

		return array_map(self::token(...), $this->read(fn (RecordStore $store): array => $store->select(self::table(), $query)->records));
	}

	/**
	 * Returns every token, newest first.
	 *
	 * @return list<Token>
	 * @throws AuthException When the tokens can't be read.
	 */
	public function all(): array
	{
		$query = new RecordQuery()->orderBy('created', Order::Desc);

		return array_map(self::token(...), $this->read(fn (RecordStore $store): array => $store->select(self::table(), $query)->records));
	}

	/**
	 * Writes that a token was used now, unless it was within the last
	 * `USE_INTERVAL`, and returns it as it's kept.
	 *
	 * @throws AuthException When it can't be saved.
	 */
	public function used(Token $token): Token
	{
		$now = $this->clock->now()->getTimestamp();

		return $token->lastUsed !== null && $token->lastUsed > $now - self::USE_INTERVAL
			? $token
			: $this->save($token->withLastUsed($now));
	}

	/**
	 * Revokes a token, deleting its record. A missing one is nothing to
	 * revoke.
	 *
	 * @throws AuthException When it can't be deleted.
	 */
	public function revoke(Token $token): void
	{
		$this->write($token->name, fn (RecordStore $store): null => $store->delete(self::table(), $token->id));
	}

	/**
	 * Revokes every token of an account, and returns how many there were.
	 *
	 * @throws AuthException When they can't be read or deleted.
	 */
	public function revokeAll(Account $account): int
	{
		$tokens = $this->forAccount($account);

		foreach ($tokens as $token) {
			$this->revoke($token);
		}

		return count($tokens);
	}

	/**
	 * Removes tokens that expired over `KEEP_EXPIRED` ago, and returns
	 * how many.
	 *
	 * @throws AuthException When they can't be read or deleted.
	 */
	public function prune(): int
	{
		$now     = $this->clock->now()->getTimestamp();
		$before  = $now - self::KEEP_EXPIRED;

		// An app's token lasts an hour and is never listed, so it goes as
		// soon as it expires (D-752).
		$expired = array_filter($this->all(), static fn (Token $token): bool => $token->isExpired($token->client === null ? $before : $now));

		foreach ($expired as $token) {
			$this->revoke($token);
		}

		return count($expired);
	}

	/**
	 * Saves a token, and returns it as kept.
	 *
	 * @throws AuthException When it can't be saved.
	 */
	private function save(Token $token): Token
	{
		$record = new Record($token->id, $token->toArray());

		$this->write($token->name, fn (RecordStore $store): Record => $store->save(self::table(), $record));

		return $token;
	}

	/**
	 * Returns what reading the table gives.
	 *
	 * @template T
	 * @param  Closure(RecordStore): T $read
	 * @return T
	 * @throws AuthException When it can't be read.
	 */
	private function read(Closure $read): mixed
	{
		try {
			return $read($this->store());
		} catch (RecordException | StorageException $e) {
			throw new AuthException(sprintf('The API tokens can\'t be read: %s', $e->getMessage()), previous: $e);
		}
	}

	/**
	 * Runs a write to the table.
	 *
	 * @param  Closure(RecordStore): mixed $write
	 * @throws AuthException When it can't be written.
	 */
	private function write(string $name, Closure $write): void
	{
		try {
			$write($this->store());
		} catch (RecordException | StorageException $e) {
			throw new AuthException(sprintf('The token "%s" couldn\'t be saved or removed: %s', $name, $e->getMessage()), previous: $e);
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
	 * Returns a record's token.
	 *
	 * @throws AuthException When it isn't one.
	 */
	private static function token(Record $record): Token
	{
		return Token::fromArray($record->fields, $record->id);
	}
}
