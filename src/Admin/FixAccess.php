<?php

/**
 * Fix access.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Closure;
use Blush\Auth\Account;
use Blush\Auth\Capability;
use Blush\Auth\ContentAction;
use Blush\Auth\Permissions;
use Blush\Content\Index\EntryFiles;
use Blush\Media\Index\MediaLibrary;

/**
 * What a Site Health fix may change for an account (D-543, D-624): the
 * entries it may edit, the media files whose details it may edit
 * (`media.edit`, D-407), and the types it may create and publish terms
 * of (D-584). Shared by the fixes run in a request and those run as a
 * job, which checks again when it runs.
 */
final readonly class FixAccess
{
	public function __construct(
		private Permissions $permissions,
		private EntryFiles $files,
		private MediaLibrary $library
	) {}

	/**
	 * Returns whether the account may edit the entry at a path.
	 *
	 * @return Closure(string): bool
	 */
	public function entries(Account $account): Closure
	{
		return function (string $path) use ($account): bool {
			$entry = $this->files->at($path);

			return $entry !== null && $this->permissions->can($account, ContentAction::Edit, $entry);
		};
	}

	/**
	 * Returns whether the account may edit the details of the media file
	 * at a key.
	 *
	 * @return Closure(string): bool
	 */
	public function media(Account $account): Closure
	{
		return function (string $key) use ($account): bool {
			$record = $this->library->find($key);

			return $record !== null && $this->permissions->mayChangeMedia($account, Capability::MediaEdit, $record->metadata()->owner);
		};
	}

	/**
	 * Returns whether the account may create and publish entries of a
	 * type, by name.
	 *
	 * @return Closure(string): bool
	 */
	public function termTypes(Account $account): Closure
	{
		return fn (string $type): bool => $this->permissions->can($account, ContentAction::Create, $type) && $this->permissions->can($account, ContentAction::Publish, $type);
	}

	/**
	 * Narrows what a fix may change to some paths (or keys), when it's
	 * given any.
	 *
	 * @param  Closure(string): bool $allowed
	 * @param  ?list<string>         $paths
	 * @return Closure(string): bool
	 */
	public static function only(Closure $allowed, ?array $paths): Closure
	{
		return $paths === null ? $allowed : static fn (string $path): bool => in_array($path, $paths, true) && $allowed($path);
	}
}
