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
 *         new Role('reviewer', 'Reviewer', ['content.*.edit', 'content.*.edit.others'])
 *     ]);
 *
 * - `roles` adds roles, or replaces a built-in one of the same name.
 *   They're config, not data in `user/`, since they decide who can do
 *   what. The admin keeps its own roles in `storage/roles.json`
 *   (D-312); a role here wins over one of the same name there, and the
 *   admin shows it read-only.
 * - `minPasswordLength` is the shortest password accepted.
 * - `maxAttempts` failed sign-ins within `lockout` seconds lock out an
 *   address and username for the rest of that time.
 * - `passwordLinkLifetime` is how many seconds a link for choosing a
 *   password lasts (D-312): a week.
 */
final readonly class AuthConfig implements Config
{
	/**
	 * @param list<Role> $roles
	 * @throws InvalidConfig
	 */
	public function __construct(
		public array $roles = [],
		public int $minPasswordLength = 12,
		public int $maxAttempts = 5,
		public int $lockout = 900,
		public int $passwordLinkLifetime = 604800
	) {
		if ($minPasswordLength < 8 || $maxAttempts < 1 || $lockout < 1 || $passwordLinkLifetime < 60) {
			throw new InvalidConfig('AuthConfig "minPasswordLength" must be at least 8, "maxAttempts" and "lockout" at least 1, and "passwordLinkLifetime" at least 60.');
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['roles', 'minPasswordLength', 'maxAttempts', 'lockout', 'passwordLinkLifetime']);

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
			minPasswordLength: $values->int('minPasswordLength', 12),
			maxAttempts: $values->int('maxAttempts', 5),
			lockout: $values->int('lockout', 900),
			passwordLinkLifetime: $values->int('passwordLinkLifetime', 604800)
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'roles'                => array_map(static fn (Role $role): array => $role->toArray(), $this->roles),
			'minPasswordLength'    => $this->minPasswordLength,
			'maxAttempts'          => $this->maxAttempts,
			'lockout'              => $this->lockout,
			'passwordLinkLifetime' => $this->passwordLinkLifetime
		];
	}
}
