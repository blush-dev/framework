<?php

/**
 * People JSON.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Psr\Clock\ClockInterface;
use Blush\Auth\Account;
use Blush\Auth\Accounts;
use Blush\Auth\BuiltInRole;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Auth\Role;
use Blush\Auth\RoleOrigin;
use Blush\Auth\Roles;
use Blush\Content\Entries;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentTypes;

/**
 * Describes accounts and roles for the admin's people screens (D-249,
 * D-312), as the account looking at them may act on them. Password
 * hashes, link hashes, and preferences stay on the server.
 */
final readonly class PeopleJson
{
	public function __construct(
		private PeopleRules $rules,
		private Permissions $permissions,
		private Accounts $accounts,
		private ClockInterface $clock,
		private Entries $content,
		private ContentTypes $types,
		private EntryHandles $handles,
		private ContentUrls $urls
	) {}

	/**
	 * Describes an account: its `username`, its `email` (D-370), its own
	 * `name` (or `null`), its `displayName` (D-370: the name, else the
	 * profile's title, else the username), `roles`, `author` (the linked
	 * profile's slug), its
	 * `profile` (D-353: `{"id", "handle", "slug", "title", "status",
	 * "url", "uses"}`, or `null` without a file), `created` and `lastLogin`
	 * (Unix times), `status`, its password `link` (`expires`, and whether
	 * it has `expired`; `null` for none), and whether the viewer
	 * `manages` it.
	 *
	 * @return array<string, mixed>
	 */
	public function account(Account $account, Account $viewer): array
	{
		$link = $account->passwordLink;

		return [
			'username'    => $account->username,
			'email'       => $account->email,
			'name'        => $account->name,
			'displayName' => $this->accounts->displayName($account),
			'roles'       => $account->roles,
			'author'      => $account->author,
			'profile'     => $this->profile($account),
			'created'     => $account->created,
			'lastLogin'   => $account->lastLogin,
			'status'      => $account->status()->value,
			'link'        => $link === null ? null : ['expires' => $link->expires, 'expired' => $link->expires <= $this->clock->now()->getTimestamp()],
			'manages'     => $this->rules->manages($viewer, $account)
		];
	}

	/**
	 * Returns an account's profile, or `null` when it links to none or
	 * the profile has no file: its `id`, `handle`, `slug`, `title`,
	 * `status`, `url` on the site, and how many published entries credit
	 * it (`uses`).
	 *
	 * @return ?array{path: string, id: ?string, type: string, handle: ?string, slug: string, title: string, status: string, url: ?string, uses: int}
	 */
	public function profile(Account $account): ?array
	{
		$profiles = $this->types->profiles();
		$entry    = $account->author === null || $profiles === null ? null : $this->content->named($profiles->name, $account->author);

		return $entry === null ? null : [
			'path'   => $entry->path,
			'id'     => $entry->id,
			'type'   => $entry->type->name,
			'handle' => $this->handles->of($entry),
			'slug'   => $entry->key,
			'title'  => $entry->title,
			'status' => $entry->status->value,
			'url'    => $this->urls->entry($entry),
			'uses'   => $this->content->termCounts($entry->type->name)[$entry->key] ?? 0
		];
	}

	/**
	 * Describes a role: its `name`, `label`, `description`,
	 * `capabilities` (`*` for all), whether it's `builtIn`, its `origin`,
	 * the `accounts` that hold it (each its `username` and
	 * `displayName`), whether the viewer may give it to accounts
	 * (`grantable`), and whether the viewer may change it (`editable`,
	 * which also needs `roles.manage`, D-362). A
	 * changed built-in also has its `defaults`.
	 *
	 * @param  list<Account> $accounts
	 * @return array<string, mixed>
	 */
	public function role(Role $role, Roles $roles, array $accounts, Account $viewer): array
	{
		$origin    = $roles->origin($role->name) ?? RoleOrigin::Config;
		$grantable = $this->rules->mayGrant($viewer, $role);
		$builtIn   = BuiltInRole::tryFrom($role->name);

		return [
			'name'         => $role->name,
			'label'        => $role->label,
			'description'  => $role->description,
			'capabilities' => $role->capabilities,
			'builtIn'      => $builtIn !== null,
			'origin'       => $origin->value,
			'accounts'     => array_values(array_map(
				fn (Account $account): array => ['username' => $account->username, 'displayName' => $this->accounts->displayName($account)],
				array_filter($accounts, static fn (Account $account): bool => in_array($role->name, $account->roles, true))
			)),
			'grantable'    => $grantable,
			'editable'     => $grantable && $origin->editable($role->name) && $this->permissions->can($viewer, Capability::RolesManage),
			...($origin === RoleOrigin::Changed && $builtIn !== null ? ['defaults' => $builtIn->capabilities()] : [])
		];
	}
}
