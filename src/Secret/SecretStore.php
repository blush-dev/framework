<?php

/**
 * Secret store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Secret;

use Closure;
use DateTimeZone;
use Psr\Clock\ClockInterface;
use Blush\Env\Env;
use Blush\Storage\Record\KeyedTable;
use Blush\Storage\Record\RecordException;
use Blush\Storage\Record\RecordStore;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageArea;
use Blush\Storage\StorageException;

/**
 * Reads and writes secrets by their definitions (D-715): the environment
 * first, read when asked, never through compiled config; then the value
 * stored in the secrets area's `secrets` table, kept by its driver. On
 * files that's `storage/secrets.json`, out of git and `user/` (D-713).
 * The table only ever holds ciphertext (`SecretCipher`), with the owner,
 * key, when, and by whom.
 *
 * `Secrets` and its scopes are how code reaches this; it checks that a
 * secret was declared, and this does the rest.
 */
final readonly class SecretStore
{
	/**
	 * The table's name.
	 */
	public const string TABLE = 'secrets';

	public function __construct(
		private Env $env,
		private SecretCipher $cipher,
		private SecretKeys $keys,
		private RecordStores|RecordStore $stores,
		private ClockInterface $clock
	) {}

	/**
	 * The secrets' table, keyed by each secret's record key
	 * (`SecretDefinition::recordKey()`).
	 */
	public static function table(): Table
	{
		return new Table(self::TABLE, StorageArea::Secrets, key: 'name', fields: ['name', 'owner']);
	}

	/**
	 * Returns where a secret's value comes from.
	 *
	 * @throws SecretStoreFailure When the table can't be read.
	 */
	public function status(SecretDefinition $definition): SecretStatus
	{
		if ($this->fromEnv($definition) !== null) {
			return SecretStatus::Environment;
		}

		$stored = $this->stored($definition);

		if ($stored === null) {
			return SecretStatus::Missing;
		}

		try {
			$this->cipher->decrypt($definition->name(), $stored);
		} catch (SecretUnreadable) {
			return SecretStatus::Unreadable;
		}

		return SecretStatus::Stored;
	}

	/**
	 * Returns a secret's value, or `null` when it's set nowhere.
	 *
	 * @throws SecretUnreadable   When it's stored but won't decrypt.
	 * @throws SecretStoreFailure When the table can't be read.
	 */
	public function get(SecretDefinition $definition): ?Secret
	{
		$env = $this->fromEnv($definition);

		if ($env !== null) {
			return new Secret($env);
		}

		$stored = $this->stored($definition);

		return $stored === null ? null : $this->cipher->decrypt($definition->name(), $stored);
	}

	/**
	 * Stores a secret's value, encrypted.
	 *
	 * @param  ?string $by The id of the account storing it, or `null` for code.
	 * @throws SecretLocked       When the environment sets it.
	 * @throws InvalidSecret      When the value is empty or there's no key.
	 * @throws SecretStoreFailure When it can't be written.
	 */
	public function set(SecretDefinition $definition, #[\SensitiveParameter] string $value, ?string $by = null): void
	{
		$this->checkWritable($definition, $value);

		$this->write($definition, $value, $by);
	}

	/**
	 * Changes a secret's value from what it is now, with nothing else
	 * writing between the read and the write: for a token a provider
	 * replaces each time it's used. `$change` gets the value (or `null`)
	 * and returns the new one.
	 *
	 * @param  Closure(?Secret): string $change
	 * @param  ?string                  $by The id of the account changing it, or `null` for code.
	 * @throws SecretLocked       When the environment sets it.
	 * @throws SecretUnreadable   When the stored value won't decrypt.
	 * @throws InvalidSecret      When the new value is empty or there's no key.
	 * @throws SecretStoreFailure When it can't be read or written.
	 */
	public function update(SecretDefinition $definition, Closure $change, ?string $by = null): Secret
	{
		if ($this->fromEnv($definition) !== null) {
			throw self::locked($definition);
		}

		$this->keys->current();

		try {
			return $this->records()->transaction(function () use ($definition, $change, $by): Secret {
				$stored = $this->stored($definition);
				$value  = $change($stored === null ? null : $this->cipher->decrypt($definition->name(), $stored));

				$this->checkWritable($definition, $value);
				$this->write($definition, $value, $by);

				return new Secret($value);
			});
		} catch (RecordException | StorageException $e) {
			throw self::failure($e);
		}
	}

	/**
	 * Removes a secret's stored value. One set in the environment stays
	 * set; a missing one is nothing to remove.
	 *
	 * @throws SecretStoreFailure When it can't be removed.
	 */
	public function remove(SecretDefinition $definition): void
	{
		try {
			$this->records()->delete($definition->recordKey());
		} catch (RecordException | StorageException $e) {
			throw self::failure($e);
		}
	}

	/**
	 * Returns every stored secret, declared or not, by full name, without
	 * their values.
	 *
	 * @return array<string, StoredSecret>
	 * @throws SecretStoreFailure When the table can't be read.
	 */
	public function all(): array
	{
		try {
			$records = $this->records()->all();
		} catch (RecordException | StorageException $e) {
			throw self::failure($e);
		}

		$all = [];

		foreach ($records as $data) {
			$owner = $data['owner'] ?? null;
			$key   = $data['key'] ?? null;
			$value = $data['value'] ?? null;

			if (! is_string($owner) || ! is_string($key)) {
				continue;
			}

			$secret = new StoredSecret(
				owner: $owner,
				key: $key,
				updated: is_string($data['updated'] ?? null) ? $data['updated'] : null,
				by: is_string($data['by'] ?? null) ? $data['by'] : null,
				keyId: is_string($value) ? SecretCipher::keyId($value) : null
			);

			$all[$secret->name()] = $secret;
		}

		ksort($all, SORT_STRING);

		return $all;
	}

	/**
	 * Encrypts every stored secret of the given definitions again under
	 * the current key, leaving those already under it. Each keeps when
	 * and by whom it was stored.
	 *
	 * @param  list<SecretDefinition> $definitions
	 * @return array{rotated: list<string>, unreadable: list<string>} Full names.
	 * @throws InvalidSecret      When there's no key.
	 * @throws SecretStoreFailure When the table can't be read or written.
	 */
	public function rotate(array $definitions): array
	{
		$current = SecretKeys::id($this->keys->current());
		$result  = ['rotated' => [], 'unreadable' => []];

		try {
			$this->records()->transaction(function () use ($definitions, $current, &$result): void {
				foreach ($definitions as $definition) {
					$data   = $this->records()->find($definition->recordKey());
					$stored = is_string($data['value'] ?? null) ? $data['value'] : null;

					if ($data === null || $stored === null || SecretCipher::keyId($stored) === $current) {
						continue;
					}

					try {
						$value = $this->cipher->decrypt($definition->name(), $stored);
					} catch (SecretUnreadable) {
						$result['unreadable'][] = $definition->name();
						continue;
					}

					$this->records()->save($definition->recordKey(), [...$data, 'value' => $this->cipher->encrypt($definition->name(), $value->reveal())]);

					$result['rotated'][] = $definition->name();
				}
			});
		} catch (RecordException | StorageException $e) {
			throw self::failure($e);
		}

		return $result;
	}

	/**
	 * Returns the secret's value from the environment, or `null` when
	 * it's unset or empty.
	 */
	private function fromEnv(SecretDefinition $definition): ?string
	{
		$value = $this->env->get($definition->env);

		return $value === null || $value === '' ? null : $value;
	}

	/**
	 * Returns the secret's stored value, still encrypted, or `null`.
	 *
	 * @throws SecretStoreFailure
	 */
	private function stored(SecretDefinition $definition): ?string
	{
		try {
			$value = $this->records()->find($definition->recordKey())['value'] ?? null;
		} catch (RecordException | StorageException $e) {
			throw self::failure($e);
		}

		return is_string($value) ? $value : null;
	}

	/**
	 * Checks that a value can be stored for a secret.
	 *
	 * @throws SecretLocked  When the environment sets it.
	 * @throws InvalidSecret When the value is empty or there's no key.
	 */
	private function checkWritable(SecretDefinition $definition, #[\SensitiveParameter] string $value): void
	{
		if ($this->fromEnv($definition) !== null) {
			throw self::locked($definition);
		}

		if ($value === '') {
			throw new InvalidSecret(sprintf('"%s" can\'t be stored empty; remove it instead.', $definition->name()));
		}

		$this->keys->current();
	}

	/**
	 * Writes a value, encrypted.
	 *
	 * @throws InvalidSecret      When there's no key.
	 * @throws SecretStoreFailure When it can't be written.
	 */
	private function write(SecretDefinition $definition, #[\SensitiveParameter] string $value, ?string $by): void
	{
		try {
			$this->records()->save($definition->recordKey(), [
				'owner'   => $definition->owner,
				'key'     => $definition->key,
				'value'   => $this->cipher->encrypt($definition->name(), $value),
				'updated' => $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
				'by'      => $by
			]);
		} catch (RecordException | StorageException $e) {
			throw self::failure($e);
		}
	}

	/**
	 * The table, through its area's store.
	 */
	private function records(): KeyedTable
	{
		return new KeyedTable($this->stores, self::table(), $this->clock);
	}

	/**
	 * The error for a secret the environment sets.
	 */
	private static function locked(SecretDefinition $definition): SecretLocked
	{
		return new SecretLocked(sprintf('"%s" is set in the environment (%s), which always wins; change it there.', $definition->name(), $definition->env));
	}

	/**
	 * The error for a table that can't be read or written.
	 */
	private static function failure(RecordException|StorageException $error): SecretStoreFailure
	{
		return new SecretStoreFailure(sprintf('The secrets couldn\'t be read or written: %s', $error->getMessage()), previous: $error);
	}
}
