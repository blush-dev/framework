<?php

/**
 * Secret scope.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Secret;

use Closure;

/**
 * One owner's secrets (D-715), by key: what `Secrets::for()` gives core
 * (`core`) and each extension (its `vendor/name`). Every secret must be
 * declared; reading one nobody declared throws, so a typo fails loudly.
 */
final readonly class SecretScope
{
	public function __construct(
		private Secrets $secrets,
		private SecretStore $store,
		public string $owner
	) {}

	/**
	 * Returns a secret's value, or `null` when it's set nowhere.
	 *
	 * @throws InvalidSecret      When it isn't declared.
	 * @throws SecretUnreadable   When it's stored but won't decrypt.
	 * @throws SecretStoreFailure When the stored secrets can't be read.
	 */
	public function get(string $key): ?Secret
	{
		return $this->store->get($this->definition($key));
	}

	/**
	 * Returns a secret's value.
	 *
	 * @throws SecretMissing      When it's set nowhere.
	 * @throws InvalidSecret      When it isn't declared.
	 * @throws SecretUnreadable   When it's stored but won't decrypt.
	 * @throws SecretStoreFailure When the stored secrets can't be read.
	 */
	public function require(string $key): Secret
	{
		$definition = $this->definition($key);

		return $this->store->get($definition) ?? throw new SecretMissing(sprintf(
			'"%s" isn\'t set: set %s in the environment, or store it.',
			$definition->name(),
			$definition->env
		));
	}

	/**
	 * Whether a secret has a value to use.
	 *
	 * @throws InvalidSecret      When it isn't declared.
	 * @throws SecretStoreFailure When the stored secrets can't be read.
	 */
	public function has(string $key): bool
	{
		return $this->status($key)->isSet();
	}

	/**
	 * Returns where a secret's value comes from.
	 *
	 * @throws InvalidSecret      When it isn't declared.
	 * @throws SecretStoreFailure When the stored secrets can't be read.
	 */
	public function status(string $key): SecretStatus
	{
		return $this->store->status($this->definition($key));
	}

	/**
	 * Stores a secret's value, encrypted.
	 *
	 * @param  ?string $by The id of the account storing it, or `null` for code.
	 * @throws InvalidSecret      When it isn't declared, the value is empty, or there's no key.
	 * @throws SecretLocked       When the environment sets it.
	 * @throws SecretStoreFailure When it can't be written.
	 */
	public function set(string $key, #[\SensitiveParameter] string $value, ?string $by = null): void
	{
		$this->store->set($this->definition($key), $value, $by);
	}

	/**
	 * Changes a secret's value from what it is now, with nothing else
	 * writing between: `$change` gets the value (or `null`) and returns
	 * the new one.
	 *
	 * @param  Closure(?Secret): string $change
	 * @param  ?string                  $by The id of the account changing it, or `null` for code.
	 * @throws InvalidSecret      When it isn't declared, the new value is empty, or there's no key.
	 * @throws SecretLocked       When the environment sets it.
	 * @throws SecretUnreadable   When the stored value won't decrypt.
	 * @throws SecretStoreFailure When it can't be read or written.
	 */
	public function update(string $key, Closure $change, ?string $by = null): Secret
	{
		return $this->store->update($this->definition($key), $change, $by);
	}

	/**
	 * Removes a secret's stored value; one set in the environment stays.
	 *
	 * @throws InvalidSecret      When it isn't declared.
	 * @throws SecretStoreFailure When it can't be removed.
	 */
	public function remove(string $key): void
	{
		$this->store->remove($this->definition($key));
	}

	/**
	 * Returns the owner's declared secret.
	 *
	 * @throws InvalidSecret
	 */
	private function definition(string $key): SecretDefinition
	{
		return $this->secrets->definition($this->owner, $key);
	}
}
