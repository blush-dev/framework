<?php

/**
 * Secret keys.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Secret;

use Blush\Env\Env;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * The keys stored secrets are encrypted with (D-715), from the
 * environment only: `SECRETS_KEY`, which encrypts, and
 * `SECRETS_KEY_PREVIOUS`, which only decrypts, while `secrets:rotate`
 * moves stored secrets from one to the other. Each is 32 random bytes in
 * base64, which `init` writes.
 *
 * A key is known by a short id, a hash of it, written beside each value
 * so a value finds the key that reads it.
 */
final class SecretKeys
{
	/**
	 * The variable holding the key that encrypts.
	 */
	public const string CURRENT = 'SECRETS_KEY';

	/**
	 * The variable holding a key kept only to decrypt.
	 */
	public const string PREVIOUS = 'SECRETS_KEY_PREVIOUS';

	/**
	 * A key's length, in bytes.
	 */
	public const int LENGTH = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES;

	/**
	 * The keys, by id, the current one first.
	 *
	 * @var array<string, string>
	 */
	private array $keys = [];

	/**
	 * The current key's id, or `null` without one.
	 */
	private ?string $current = null;

	/**
	 * What's wrong with the keys as set, if anything.
	 *
	 * @var list<Text>
	 */
	private array $problems = [];

	public function __construct(
		Env $env,
		private readonly Messages $messages = new Messages()
	) {
		foreach ([self::CURRENT, self::PREVIOUS] as $name) {
			$value = trim($env->get($name) ?? '');

			if ($value === '') {
				continue;
			}

			$key = base64_decode($value, true);

			if ($key === false || strlen($key) !== self::LENGTH) {
				$this->problems[] = $messages->t('secret.secret_keys.not_a_key', '{variable} isn\'t a key: it needs {length} random bytes in base64.', variable: $name, length: self::LENGTH);
				continue;
			}

			$id = self::id($key);

			$this->keys[$id] ??= $key;

			if ($name === self::CURRENT) {
				$this->current = $id;
			}
		}
	}

	/**
	 * Returns a new key, in base64, as it's written to `.env`.
	 */
	public static function generate(): string
	{
		return base64_encode(sodium_crypto_aead_xchacha20poly1305_ietf_keygen());
	}

	/**
	 * Returns a key's id: eight hex digits of a keyed hash of it.
	 */
	public static function id(#[\SensitiveParameter] string $key): string
	{
		return substr(bin2hex(sodium_crypto_generichash('secret key id', $key, 16)), 0, 8);
	}

	/**
	 * Whether there's a key to encrypt with.
	 */
	public function canEncrypt(): bool
	{
		return $this->current !== null;
	}

	/**
	 * Returns the current key's id, or `null`.
	 */
	public function currentId(): ?string
	{
		return $this->current;
	}

	/**
	 * Returns the current key.
	 *
	 * @throws InvalidSecret When there's none.
	 */
	public function current(): string
	{
		if ($this->current === null) {
			throw new InvalidSecret((string) $this->messages->t('secret.secret_keys.no_key', 'Secrets can\'t be stored without a key: set {variable} (run "init" to add one).', variable: self::CURRENT));
		}

		return $this->keys[$this->current];
	}

	/**
	 * Returns the key with an id, or `null` when the site has none by it.
	 */
	public function find(string $id): ?string
	{
		return $this->keys[$id] ?? null;
	}

	/**
	 * Returns what's wrong with the keys as set: a value that isn't a
	 * key. Without either, there's nothing wrong, only nothing stored.
	 *
	 * @return list<Text>
	 */
	public function problems(): array
	{
		return $this->problems;
	}

	/**
	 * What dumps show: ids, never keys.
	 *
	 * @return array{current: ?string, keys: list<string>}
	 */
	public function __debugInfo(): array
	{
		return ['current' => $this->current, 'keys' => array_keys($this->keys)];
	}
}
