<?php

/**
 * OAuth clients.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp\OAuth;

use Closure;
use JsonException;
use Psr\Clock\ClockInterface;
use Uri\Rfc3986\Uri;
use Blush\Embed\Fetcher;
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
 * The apps that may ask to connect (D-752), in the `oauth_clients` table,
 * kept with accounts (on files, `storage/oauth/clients`). Two ways in,
 * with no admin screen for either: an app registers itself (dynamic
 * registration, RFC 7591) and gets a `client_id`, or it goes by the
 * address of its own metadata document, read once a day, which proves
 * where it's from. A client can do nothing until a person approves it;
 * one nobody approves within a day is removed by the pruning job. Only
 * HTTPS addresses, and `http` on the person's own computer, are taken to
 * send people back to.
 */
final readonly class OAuthClients
{
	public const string TABLE = 'oauth_clients';

	/**
	 * How long, in seconds, a client nobody approved is kept.
	 */
	public const int KEEP_UNAPPROVED = 86400;

	/**
	 * How long, in seconds, a metadata document is kept before it's read
	 * again.
	 */
	public const int REFRESH = 86400;

	public function __construct(
		private RecordStores|RecordStore $stores,
		private ClockInterface $clock,
		private Fetcher $fetcher,
		private RequestMemo $memo = new RequestMemo()
	) {}

	public static function table(): Table
	{
		return new Table(self::TABLE, StorageArea::Accounts, fields: ['clientId', 'registered', 'approved']);
	}

	/**
	 * Registers an app from what it sends (RFC 7591): `client_name`,
	 * `redirect_uris`, and, optionally, `client_uri`. Only public clients
	 * (`token_endpoint_auth_method: none`), which prove themselves with
	 * PKCE.
	 *
	 * @param  array<mixed> $metadata
	 * @throws OAuthError
	 */
	public function register(array $metadata): OAuthClient
	{
		$method = $metadata['token_endpoint_auth_method'] ?? 'none';

		if ($method !== 'none') {
			throw new OAuthError('invalid_client_metadata', 'Only public clients register here: "token_endpoint_auth_method" is "none", with PKCE.', status: 400);
		}

		$now    = $this->clock->now();
		$record = Record::create($now);
		$client = new OAuthClient(
			id: $record->id,
			clientId: $record->id,
			name: self::name($metadata['client_name'] ?? null),
			redirectUris: self::redirectUris($metadata['redirect_uris'] ?? null),
			uri: self::address($metadata['client_uri'] ?? null),
			registered: $now->getTimestamp()
		);

		return $this->save($client);
	}

	/**
	 * Returns the client a `client_id` names: one that registered, or one
	 * whose metadata document is at that address, read now when it's
	 * new or a day old; `null` when there's none.
	 *
	 * @throws OAuthError When its metadata document can't be read or isn't one.
	 */
	public function find(string $clientId): ?OAuthClient
	{
		$stored = $this->stored($clientId);

		if (! str_starts_with($clientId, 'https://')) {
			return $stored;
		}

		if ($stored !== null && $stored->registered > $this->clock->now()->getTimestamp() - self::REFRESH) {
			return $stored;
		}

		return $this->fromDocument($clientId, $stored);
	}

	/**
	 * Returns a client by its record's id, or `null`.
	 *
	 * @throws OAuthError
	 */
	public function byId(string $id): ?OAuthClient
	{
		$record = Uuid::isValid($id) ? $this->read(fn (RecordStore $store): ?Record => $store->find(self::table(), $id)) : null;

		return $record === null ? null : $this->client($record);
	}

	/**
	 * Saves a client as approved, once someone has.
	 *
	 * @throws OAuthError
	 */
	public function approve(OAuthClient $client): OAuthClient
	{
		return $client->approved ? $client : $this->save($client->withApproved());
	}

	/**
	 * Removes clients nobody approved within a day, and returns how many.
	 *
	 * @throws OAuthError
	 */
	public function prune(): int
	{
		$before = $this->clock->now()->getTimestamp() - self::KEEP_UNAPPROVED;
		$query  = new RecordQuery()->where('approved', '=', false)->where('registered', '<', $before);
		$old    = $this->read(fn (RecordStore $store): array => $store->select(self::table(), $query)->records);

		foreach ($old as $record) {
			$this->write(fn (RecordStore $store): null => $store->delete(self::table(), $record->id));
		}

		return count($old);
	}

	/**
	 * Reads a client's metadata document, and saves the client it
	 * describes.
	 *
	 * @throws OAuthError
	 */
	private function fromDocument(string $clientId, ?OAuthClient $stored): OAuthClient
	{
		if (! self::isPublicAddress($clientId)) {
			throw OAuthError::client('The client\'s metadata address isn\'t one on the internet.');
		}

		$body = $this->fetcher->get($clientId, 5);

		try {
			$document = $body === null ? null : json_decode($body, true, 16, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			$document = null;
		}

		if (! is_array($document) || ($document['client_id'] ?? null) !== $clientId) {
			throw OAuthError::client('The client\'s metadata document can\'t be read, or doesn\'t name itself by its address.');
		}

		if (($document['token_endpoint_auth_method'] ?? 'none') !== 'none') {
			throw OAuthError::client('Only public clients connect here, with PKCE.');
		}

		$client = new OAuthClient(
			id: $stored->id ?? Record::create($this->clock->now())->id,
			clientId: $clientId,
			name: self::name($document['client_name'] ?? null),
			redirectUris: self::redirectUris($document['redirect_uris'] ?? null),
			uri: self::address($document['client_uri'] ?? null),
			registered: $this->clock->now()->getTimestamp(),
			approved: $stored->approved ?? false
		);

		return $this->save($client);
	}

	/**
	 * Returns a stored client by the `client_id` it goes by.
	 *
	 * @throws OAuthError
	 */
	private function stored(string $clientId): ?OAuthClient
	{
		$query  = new RecordQuery()->where('clientId', '=', $clientId)->limit(1);
		$record = $this->read(fn (RecordStore $store): ?Record => $store->select(self::table(), $query)->first());

		return $record === null ? null : $this->client($record);
	}

	/**
	 * Saves a client, and returns it.
	 *
	 * @throws OAuthError
	 */
	private function save(OAuthClient $client): OAuthClient
	{
		$this->write(fn (RecordStore $store): Record => $store->save(self::table(), new Record($client->id, $client->toArray())));

		return $client;
	}

	/**
	 * A client's name: what it says, on one line, up to 100 characters.
	 */
	private static function name(mixed $name): string
	{
		$name = is_string($name) ? trim((string) preg_replace('/\s+/u', ' ', $name)) : '';

		return $name === '' ? 'An app' : mb_substr($name, 0, 100);
	}

	/**
	 * The addresses a client may be sent back to: HTTPS ones, and `http`
	 * on the person's own computer, with no fragment.
	 *
	 * @return list<string>
	 * @throws OAuthError When there are none, or one won't do.
	 */
	private static function redirectUris(mixed $uris): array
	{
		if (! is_array($uris) || $uris === [] || count($uris) > 10) {
			throw new OAuthError('invalid_redirect_uri', 'Send "redirect_uris": from 1 to 10 addresses to send people back to.', status: 400);
		}

		$valid = [];

		foreach ($uris as $uri) {
			$parsed = is_string($uri) ? Uri::parse($uri) : null;
			$local  = $parsed !== null && $parsed->getScheme() === 'http' && in_array($parsed->getHost(), ['127.0.0.1', '[::1]', 'localhost'], true);

			if ($parsed === null || $parsed->getFragment() !== null || ! ($parsed->getScheme() === 'https' || $local)) {
				throw new OAuthError('invalid_redirect_uri', sprintf('"%s" can\'t be a redirect address: only HTTPS ones, or http on the person\'s own computer, without a fragment.', is_string($uri) ? $uri : ''), status: 400);
			}

			$valid[] = (string) $uri;
		}

		return array_values(array_unique($valid));
	}

	/**
	 * A client's own address, when it's an HTTPS one.
	 */
	private static function address(mixed $uri): ?string
	{
		return is_string($uri) && str_starts_with($uri, 'https://') && Uri::parse($uri) !== null ? $uri : null;
	}

	/**
	 * Whether an address's host is on the internet: not this computer's,
	 * nor a private or reserved network's, so reading a client's document
	 * can't reach into the site's own network.
	 */
	private static function isPublicAddress(string $address): bool
	{
		$host = Uri::parse($address)?->getHost();

		if ($host === null || $host === '' || strtolower($host) === 'localhost') {
			return false;
		}

		$ips = filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false ? [trim($host, '[]')] : (gethostbynamel($host) ?: []);

		return $ips !== [] && array_all($ips, static fn (string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false);
	}

	/**
	 * @template T
	 * @param  Closure(RecordStore): T $read
	 * @return T
	 * @throws OAuthError
	 */
	private function read(Closure $read): mixed
	{
		try {
			return $read($this->store());
		} catch (RecordException | StorageException $e) {
			throw new OAuthError('server_error', sprintf('The OAuth clients can\'t be read: %s', $e->getMessage()), false, 500);
		}
	}

	/**
	 * @param  Closure(RecordStore): mixed $write
	 * @throws OAuthError
	 */
	private function write(Closure $write): void
	{
		try {
			$write($this->store());
		} catch (RecordException | StorageException $e) {
			throw new OAuthError('server_error', sprintf('The OAuth client couldn\'t be saved: %s', $e->getMessage()), false, 500);
		} finally {
			$this->memo->forget();
		}
	}

	/**
	 * Returns a record's client, built once a request (D-807).
	 */
	private function client(Record $record): OAuthClient
	{
		return $this->memo->remember("oauth_client|{$record->id}|{$record->version}", static fn (): OAuthClient => OAuthClient::fromArray($record->fields, $record->id));
	}

	/**
	 * @throws StorageException
	 */
	private function store(): RecordStore
	{
		return $this->stores instanceof RecordStores ? $this->stores->store(self::table()) : $this->stores;
	}
}
