<?php

/**
 * Auth config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;

/**
 * Account and sign-in settings, from `config/auth.php` (D-217):
 *
 *     return new AuthConfig(roles: [
 *         new Role('reviewer', 'Reviewer', ['content.edit', 'content.edit.others'])
 *     ]);
 *
 * - `roles` adds roles, or replaces a built-in one of the same name.
 *   They're config, not data in `user/`, since they decide who can do
 *   what.
 * - `authorTaxonomy` is the taxonomy an account's `author` belongs to
 *   (the built-in `author` type, unless a site renamed it).
 * - `minPasswordLength` is the shortest password accepted.
 * - `maxAttempts` failed sign-ins within `lockout` seconds lock out an
 *   address and username for the rest of that time.
 */
final readonly class AuthConfig implements Config
{
	/**
	 * @param list<Role> $roles
	 * @throws InvalidConfig
	 */
	public function __construct(
		public array $roles = [],
		public string $authorTaxonomy = 'author',
		public int $minPasswordLength = 12,
		public int $maxAttempts = 5,
		public int $lockout = 900
	) {
		if ($minPasswordLength < 8 || $maxAttempts < 1 || $lockout < 1) {
			throw new InvalidConfig('AuthConfig "minPasswordLength" must be at least 8, and "maxAttempts" and "lockout" at least 1.');
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['roles', 'authorTaxonomy', 'minPasswordLength', 'maxAttempts', 'lockout']);

		$roles = [];

		foreach (is_array($data['roles'] ?? null) ? $data['roles'] : [] as $role) {
			try {
				$roles[] = $role instanceof Role ? $role : Role::fromArray(is_array($role) ? $role : []);
			} catch (AuthException $e) {
				throw new InvalidConfig(sprintf('AuthConfig "roles": %s', $e->getMessage()), previous: $e);
			}
		}

		return new static(
			roles: $roles,
			authorTaxonomy: $values->string('authorTaxonomy', 'author'),
			minPasswordLength: $values->int('minPasswordLength', 12),
			maxAttempts: $values->int('maxAttempts', 5),
			lockout: $values->int('lockout', 900)
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'roles'             => array_map(static fn (Role $role): array => $role->toArray(), $this->roles),
			'authorTaxonomy'    => $this->authorTaxonomy,
			'minPasswordLength' => $this->minPasswordLength,
			'maxAttempts'       => $this->maxAttempts,
			'lockout'           => $this->lockout
		];
	}
}
