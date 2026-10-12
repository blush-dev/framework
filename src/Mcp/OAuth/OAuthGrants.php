<?php

/**
 * OAuth grants.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp\OAuth;

use Closure;
use Psr\Clock\ClockInterface;
use Blush\Storage\Record\Order;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordException;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordStore;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageArea;
use Blush\Storage\StorageException;
use Blush\Support\RequestMemo;
use Blush\Support\Uuid;

/**
 * People's approvals of apps, and the codes and refresh tokens OAuth hands
 * out for them (D-752), in the `oauth_grants` and `oauth_secrets` tables,
 * kept with accounts (on files, under `storage/oauth`). A secret's text is
 * `{id}.{secret}`; only a SHA-256 of the secret is kept.
 */
final readonly class OAuthGrants
{
	public const string GRANTS = 'oauth_grants';

	public const string SECRETS = 'oauth_secrets';

	public function __construct(
		private RecordStores|RecordStore $stores,
		private ClockInterface $clock,
		private RequestMemo $memo = new RequestMemo()
	) {}

	public static function grants(): Table
	{
		return new Table(self::GRANTS, StorageArea::Accounts, fields: ['account', 'client']);
	}

	public static function secrets(): Table
	{
		return new Table(self::SECRETS, StorageArea::Accounts, fields: ['grant', 'kind', 'expires']);
	}

	/**
	 * Returns an account's grant for a client (by its record's id), or
	 * `null`.
	 *
	 * @throws OAuthError
	 */
	public function find(string $account, string $client): ?OAuthGrant
	{
		$query  = new RecordQuery()->where('account', '=', $account)->where('client', '=', $client)->limit(1);
		$record = $this->read(self::grants(), fn (RecordStore $store): ?Record => $store->select(self::grants(), $query)->first());

		return $record === null ? null : $this->grant($record);
	}

	/**
	 * Returns a grant by id, or `null`.
	 *
	 * @throws OAuthError
	 */
	public function byId(string $id): ?OAuthGrant
	{
		$record = Uuid::isValid($id) ? $this->read(self::grants(), fn (RecordStore $store): ?Record => $store->find(self::grants(), $id)) : null;

		return $record === null ? null : $this->grant($record);
	}

	/**
	 * Returns an account's grants, newest first.
	 *
	 * @return list<OAuthGrant>
	 * @throws OAuthError
	 */
	public function forAccount(string $account): array
	{
		$query = new RecordQuery()->where('account', '=', $account)->orderBy('approved', Order::Desc);

		return array_map(
			$this->grant(...),
			$this->read(self::grants(), fn (RecordStore $store): array => $store->select(self::grants(), $query)->records)
		);
	}

	/**
	 * Saves a grant, and returns it.
	 *
	 * @throws OAuthError
	 */
	public function save(OAuthGrant $grant): OAuthGrant
	{
		$this->write(self::grants(), fn (RecordStore $store): Record => $store->save(self::grants(), new Record($grant->id, $grant->toArray())));

		return $grant;
	}

	/**
	 * Makes a new grant's id.
	 */
	public function newId(): string
	{
		return Uuid::v7($this->clock->now());
	}

	/**
	 * Deletes a grant and its codes and refresh tokens.
	 *
	 * @throws OAuthError
	 */
	public function delete(OAuthGrant $grant): void
	{
		foreach ($this->secretsOf($grant->id) as $secret) {
			$this->write(self::secrets(), fn (RecordStore $store): null => $store->delete(self::secrets(), $secret->id));
		}

		$this->write(self::grants(), fn (RecordStore $store): null => $store->delete(self::grants(), $grant->id));
	}

	/**
	 * Hands out a code or refresh token for a grant, lasting some seconds,
	 * and returns its text, shown this once.
	 *
	 * @throws OAuthError
	 */
	public function issue(string $kind, string $grant, int $lifetime, string $redirectUri = '', string $challenge = ''): string
	{
		$now    = $this->clock->now();
		$secret = bin2hex(random_bytes(32));
		$id     = Uuid::v7($now);
		$stored = new OAuthSecret($id, $kind, $grant, hash('sha256', $secret), $now->getTimestamp() + $lifetime, false, $redirectUri, $challenge);

		$this->saveSecret($stored);

		return "{$id}.{$secret}";
	}

	/**
	 * Returns the code or refresh token a text names, when its secret is
	 * right and it's of the kind, used or not, or `null`. Whether it has
	 * expired or been used is the caller's.
	 *
	 * @throws OAuthError
	 */
	public function secret(string $text, string $kind): ?OAuthSecret
	{
		[$id, $secret] = array_pad(explode('.', trim($text), 2), 2, '');

		if (! Uuid::isValid($id) || $secret === '') {
			return null;
		}

		$record = $this->read(self::secrets(), fn (RecordStore $store): ?Record => $store->find(self::secrets(), $id));
		$found  = $record === null ? null : OAuthSecret::fromArray($record->fields, $record->id);

		return $found !== null && $found->kind === $kind && $found->accepts($secret) ? $found : null;
	}

	/**
	 * Saves a code or refresh token as used.
	 *
	 * @throws OAuthError
	 */
	public function used(OAuthSecret $secret): void
	{
		$this->saveSecret($secret->withUsed());
	}

	/**
	 * Removes codes and refresh tokens that expired, and returns how many.
	 *
	 * @throws OAuthError
	 */
	public function prune(): int
	{
		$query   = new RecordQuery()->where('expires', '<', $this->clock->now()->getTimestamp());
		$expired = $this->read(self::secrets(), fn (RecordStore $store): array => $store->select(self::secrets(), $query)->records);

		foreach ($expired as $record) {
			$this->write(self::secrets(), fn (RecordStore $store): null => $store->delete(self::secrets(), $record->id));
		}

		return count($expired);
	}

	/**
	 * Returns a grant's codes and refresh tokens.
	 *
	 * @return list<OAuthSecret>
	 * @throws OAuthError
	 */
	private function secretsOf(string $grant): array
	{
		$query = new RecordQuery()->where('grant', '=', $grant);

		return array_map(
			static fn (Record $record): OAuthSecret => OAuthSecret::fromArray($record->fields, $record->id),
			$this->read(self::secrets(), fn (RecordStore $store): array => $store->select(self::secrets(), $query)->records)
		);
	}

	/**
	 * @throws OAuthError
	 */
	private function saveSecret(OAuthSecret $secret): void
	{
		$this->write(self::secrets(), fn (RecordStore $store): Record => $store->save(self::secrets(), new Record($secret->id, $secret->toArray())));
	}

	/**
	 * @template T
	 * @param  Closure(RecordStore): T $read
	 * @return T
	 * @throws OAuthError
	 */
	private function read(Table $table, Closure $read): mixed
	{
		try {
			return $read($this->store($table));
		} catch (RecordException | StorageException $e) {
			throw new OAuthError('server_error', sprintf('Connected apps can\'t be read: %s', $e->getMessage()), false, 500);
		}
	}

	/**
	 * @param  Closure(RecordStore): mixed $write
	 * @throws OAuthError
	 */
	private function write(Table $table, Closure $write): void
	{
		try {
			$write($this->store($table));
		} catch (RecordException | StorageException $e) {
			throw new OAuthError('server_error', sprintf('A connected app couldn\'t be saved: %s', $e->getMessage()), false, 500);
		} finally {
			$this->memo->forget();
		}
	}

	/**
	 * Returns a record's grant, built once a request (D-807).
	 */
	private function grant(Record $record): OAuthGrant
	{
		return $this->memo->remember("oauth_grant|{$record->id}|{$record->version}", static fn (): OAuthGrant => OAuthGrant::fromArray($record->fields, $record->id));
	}

	/**
	 * @throws StorageException
	 */
	private function store(Table $table): RecordStore
	{
		return $this->stores instanceof RecordStores ? $this->stores->store($table) : $this->stores;
	}
}
