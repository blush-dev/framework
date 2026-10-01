<?php

/**
 * File role store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

use JsonException;
use Override;
use Blush\Core\Paths;
use Blush\Support\Filesystem;
use Blush\Support\FilesystemException;

/**
 * Keeps the admin's roles in `storage/roles.json` (D-312), beside the
 * accounts and, like them, outside git and `user/`: `{"roles": [...]}`,
 * each as `Role::toArray()` writes it. No file is no roles.
 */
final readonly class FileRoleStore implements RoleStore
{
	public function __construct(
		private Paths $paths,
		private Filesystem $filesystem
	) {}

	/**
	 * Returns the file's path.
	 */
	public function path(): string
	{
		return "{$this->paths->storage}/roles.json";
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function all(): array
	{
		$contents = @file_get_contents($this->path());

		if ($contents === false) {
			return [];
		}

		$relative = $this->paths->relative($this->path());

		try {
			$data = json_decode($contents, true, 16, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new AuthException(sprintf('%s isn\'t valid JSON: %s', $relative, $e->getMessage()), previous: $e);
		}

		$roles = is_array($data) ? ($data['roles'] ?? null) : null;

		if (! is_array($roles) || ! array_is_list($roles)) {
			throw new AuthException(sprintf('%s needs a list of "roles".', $relative));
		}

		return array_map(static function (mixed $role) use ($relative): Role {
			try {
				return Role::fromArray(is_array($role) ? $role : []);
			} catch (AuthException $e) {
				throw new AuthException(sprintf('%s: %s', $relative, $e->getMessage()), previous: $e);
			}
		}, $roles);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function save(array $roles): void
	{
		try {
			$this->filesystem->writeAtomic(
				$this->path(),
				json_encode(['roles' => array_map(static fn (Role $role): array => $role->toArray(), $roles)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n",
				0660
			);
		} catch (FilesystemException | JsonException $e) {
			throw new AuthException(sprintf('The roles couldn\'t be saved: %s', $e->getMessage()), previous: $e);
		}
	}
}
