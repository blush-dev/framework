<?php

/**
 * In-memory role store, for tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Auth;

use Override;
use Blush\Auth\Role;
use Blush\Auth\RoleStore;

/**
 * Keeps roles in memory.
 */
final class MemoryRoleStore implements RoleStore
{
	/**
	 * @param list<Role> $roles
	 */
	public function __construct(private array $roles = [])
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function all(): array
	{
		return $this->roles;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function save(array $roles): void
	{
		$this->roles = $roles;
	}
}
