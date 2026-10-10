<?php

/**
 * Secrets.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Secret;

/**
 * Where core and extensions reach their secrets (D-715): credentials for
 * other services, set by the operator in the environment or stored,
 * encrypted, at runtime. Each is declared first, in a provider's
 * `register()`, then read through its owner's scope:
 *
 *     $this->container->resolving(Secrets::class, static function (object $secrets): void {
 *         if ($secrets instanceof Secrets) {
 *             $secrets->define(new SecretDefinition('acme/analytics', 'ga_refresh_token', 'Google refresh token'));
 *         }
 *     });
 *
 *     $token = $secrets->for('acme/analytics')->require('ga_refresh_token');
 *     $client->withToken($token->reveal());
 *
 * A scope reaches only its owner's secrets, which guards against
 * mistakes, not a hostile plugin: plugins run in the same PHP process
 * and can read the environment themselves. Installing a plugin trusts it.
 */
final class Secrets
{
	/**
	 * The declared secrets, by full name.
	 *
	 * @var array<string, SecretDefinition>
	 */
	private array $definitions = [];

	public function __construct(
		private readonly SecretStore $store
	) {}

	/**
	 * Declares a secret, replacing one by the same name.
	 */
	public function define(SecretDefinition $definition): void
	{
		$this->definitions[$definition->name()] = $definition;
	}

	/**
	 * Returns an owner's scope: `core`, or an extension's `vendor/name`.
	 *
	 * @throws InvalidSecret When the owner can't own secrets.
	 */
	public function for(string $owner): SecretScope
	{
		if (! SecretDefinition::isOwner($owner)) {
			throw new InvalidSecret(sprintf('"%s" can\'t own secrets; use "%s" or an extension\'s "vendor/name".', $owner, SecretDefinition::CORE));
		}

		return new SecretScope($this, $this->store, $owner);
	}

	/**
	 * Returns a declared secret.
	 *
	 * @throws InvalidSecret When nobody declared it.
	 */
	public function definition(string $owner, string $key): SecretDefinition
	{
		return $this->definitions["{$owner}.{$key}"] ?? throw new InvalidSecret(sprintf(
			'"%s.%s" isn\'t a declared secret; declare it with Secrets::define() first.',
			$owner,
			$key
		));
	}

	/**
	 * Returns a declared secret by its full name, or `null`.
	 */
	public function find(string $name): ?SecretDefinition
	{
		return $this->definitions[$name] ?? null;
	}

	/**
	 * Returns every declared secret, by full name.
	 *
	 * @return array<string, SecretDefinition>
	 */
	public function definitions(): array
	{
		$definitions = $this->definitions;

		ksort($definitions, SORT_STRING);

		return $definitions;
	}
}
