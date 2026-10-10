<?php

/**
 * Secret definition.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Secret;

use Blush\Extension\ExtensionName;

/**
 * A secret core or an extension uses (D-715), declared before it's read:
 * its owner (`core`, or the extension's `vendor/name`), its key within
 * the owner, what it's for, and the environment variable that sets it.
 *
 *     new SecretDefinition('acme/analytics', 'ga_refresh_token', 'Google refresh token', required: true);
 *
 * Its full name is `{owner}.{key}` (`acme/analytics.ga_refresh_token`).
 * A key is lowercase letters, digits, and `_`, so the last `.` always
 * splits a name. The variable defaults to one made from the name
 * (`ACME_ANALYTICS_GA_REFRESH_TOKEN`); the variables that open the store
 * or that the store isn't for (`SECRETS_KEY`, `APP_SECRET`, …) can't be
 * one.
 */
final readonly class SecretDefinition
{
	/**
	 * Core's owner name (D-714).
	 */
	public const string CORE = 'core';

	/**
	 * Matches a key.
	 */
	public const string KEY_PATTERN = '/\A[a-z0-9][a-z0-9_]*\z/';

	/**
	 * Matches an environment variable's name.
	 */
	public const string ENV_PATTERN = '/\A[A-Z_][A-Z0-9_]*\z/';

	/**
	 * Variables no secret can be read from: the store's own keys, and
	 * the secrets that must be there before anything is read.
	 *
	 * @var list<string>
	 */
	public const array RESERVED_ENV = [SecretKeys::CURRENT, SecretKeys::PREVIOUS, 'APP_SECRET', 'PUBLISH_SECRET'];

	/**
	 * The environment variable that sets the secret.
	 */
	public string $env;

	/**
	 * @throws InvalidSecret When the owner, key, or variable can't be a secret's.
	 */
	public function __construct(
		public string $owner,
		public string $key,
		public string $label = '',
		public string $description = '',
		?string $env = null,
		public bool $required = false
	) {
		if (! self::isOwner($owner)) {
			throw new InvalidSecret(sprintf('"%s" can\'t own a secret; use "%s" or an extension\'s "vendor/name".', $owner, self::CORE));
		}

		if (preg_match(self::KEY_PATTERN, $key) !== 1) {
			throw new InvalidSecret(sprintf('"%s" can\'t be a secret\'s key; use lowercase letters, digits, and "_".', $key));
		}

		$this->env = $env ?? strtoupper((string) preg_replace('/[^a-z0-9]+/', '_', "{$owner}_{$key}"));

		if (preg_match(self::ENV_PATTERN, $this->env) !== 1) {
			throw new InvalidSecret(sprintf('"%s" can\'t be an environment variable\'s name; use uppercase letters, digits, and "_".', $this->env));
		}

		if (in_array($this->env, self::RESERVED_ENV, true)) {
			throw new InvalidSecret(sprintf('"%s" can\'t hold a stored secret; it\'s read only from the environment.', $this->env));
		}
	}

	/**
	 * Returns whether a string can own secrets: core, or an extension's
	 * name.
	 */
	public static function isOwner(string $owner): bool
	{
		return $owner === self::CORE || ExtensionName::isValid($owner);
	}

	/**
	 * Returns the full name, `{owner}.{key}`.
	 */
	public function name(): string
	{
		return "{$this->owner}.{$this->key}";
	}

	/**
	 * Returns the name the secret's record is kept by: the full name with
	 * `/` as `__` (as settings groups are kept, D-673), which a table's
	 * key allows. No extension name holds `__`, so no two secrets share
	 * one.
	 */
	public function recordKey(): string
	{
		return str_replace('/', '__', $this->name());
	}

	/**
	 * Returns the label, or the full name without one.
	 */
	public function title(): string
	{
		return $this->label === '' ? $this->name() : $this->label;
	}
}
