<?php

/**
 * Secret cipher.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Secret;

use SodiumException;
use Blush\Translation\Messages;

/**
 * Encrypts and decrypts stored secrets (D-715), the only code that does:
 * XChaCha20-Poly1305 (libsodium) with a random nonce each time, and the
 * secret's full name as associated data, so a value copied to another
 * name won't decrypt. A stored value is
 *
 *     v1:{key id}:{nonce and ciphertext, base64url}
 *
 * The version and key id are bound in too, so neither can be changed.
 */
final readonly class SecretCipher
{
	/**
	 * The format's version.
	 */
	public const string VERSION = 'v1';

	public function __construct(
		private SecretKeys $keys,
		private Messages $messages = new Messages()
	) {}

	/**
	 * Encrypts a secret's value under the current key.
	 *
	 * @throws InvalidSecret When there's no key.
	 */
	public function encrypt(string $name, #[\SensitiveParameter] string $value): string
	{
		$key   = $this->keys->current();
		$id    = (string) $this->keys->currentId();
		$nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);

		try {
			$sealed = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($value, self::data($id, $name), $nonce, $key);
		} catch (SodiumException $e) {
			throw new InvalidSecret((string) $this->messages->t('secret.secret_cipher.not_encrypted', '"{name}" couldn\'t be encrypted: {error}', name: $name, error: $e->getMessage()), previous: $e);
		}

		return sprintf('%s:%s:%s', self::VERSION, $id, sodium_bin2base64($nonce . $sealed, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING));
	}

	/**
	 * Decrypts a stored value.
	 *
	 * @throws SecretUnreadable When it isn't one, its key is gone, or it won't decrypt as this name's.
	 */
	public function decrypt(string $name, string $stored): Secret
	{
		$parts = explode(':', $stored, 3);

		if (count($parts) !== 3 || $parts[0] !== self::VERSION) {
			throw new SecretUnreadable((string) $this->messages->t('secret.secret_cipher.unknown_form', '"{name}" isn\'t stored in a form this site reads.', name: $name));
		}

		[, $id, $body] = $parts;

		$key = $this->keys->find($id) ?? throw new SecretUnreadable((string) $this->messages->t(
			'secret.secret_cipher.key_missing',
			'"{name}" was stored under a key this site doesn\'t have ({id}); set it again, or put that key back in {variable}.',
			name: $name,
			id: $id,
			variable: SecretKeys::PREVIOUS
		));

		$length = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

		try {
			$bytes = sodium_base642bin($body, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
			$value = strlen($bytes) > $length
				? sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($bytes, $length), self::data($id, $name), substr($bytes, 0, $length), $key)
				: false;
		} catch (SodiumException) {
			$value = false;
		}

		if ($value === false) {
			throw new SecretUnreadable((string) $this->messages->t('secret.secret_cipher.wont_decrypt', '"{name}" won\'t decrypt: it was changed, or stored under another name.', name: $name));
		}

		return new Secret($value);
	}

	/**
	 * Returns the id of the key a stored value was written under, or
	 * `null` for a value that isn't one.
	 */
	public static function keyId(string $stored): ?string
	{
		$parts = explode(':', $stored, 3);

		return count($parts) === 3 && $parts[0] === self::VERSION ? $parts[1] : null;
	}

	/**
	 * The associated data: what a value is bound to.
	 */
	private static function data(string $id, string $name): string
	{
		return sprintf('%s:%s:%s', self::VERSION, $id, $name);
	}
}
