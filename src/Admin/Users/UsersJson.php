<?php

/**
 * Users JSON.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Users;

use Psr\Clock\ClockInterface;
use Blush\Admin\EntryHandles;
use Blush\Auth\Account;
use Blush\Auth\AccountProfiles;
use Blush\Auth\Avatars;
use Blush\Auth\BuiltInRole;
use Blush\Auth\Capabilities;
use Blush\Auth\Capability;
use Blush\Auth\EmailChanges;
use Blush\Auth\ContentAction;
use Blush\Auth\Permissions;
use Blush\Auth\Role;
use Blush\Auth\RoleOrigin;
use Blush\Auth\Roles;
use Blush\Cache\CacheException;
use Blush\Content\Entries;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;

/**
 * Describes accounts and roles for the admin's Users screens (D-249,
 * D-312), as the account looking at them may act on them. Password
 * hashes, link hashes, and preferences stay on the server.
 */
final readonly class UsersJson
{
	public function __construct(
		private UserRules $rules,
		private Permissions $permissions,
		private AccountProfiles $accounts,
		private ClockInterface $clock,
		private Entries $content,
		private EntryHandles $handles,
		private ContentUrls $urls,
		private Capabilities $capabilities,
		private ContentTypes $types,
		private Avatars $avatars,
		private EmailChanges $changes
	) {}

	/**
	 * Describes the site's capabilities, or some of them, as a role's
	 * screen and an API token's draw them: each its `name`, `label`, and
	 * `group`, and a content capability's `type` and `action` (D-359).
	 *
	 * @param  ?list<string> $only Only these names, or `null` for all.
	 * @return list<array<string, ?string>>
	 */
	public function capabilities(?array $only = null): array
	{
		$capabilities = [];

		foreach ($this->capabilities->all() as $name => $label) {
			if ($only !== null && ! in_array($name, $only, true)) {
				continue;
			}

			[$type, $action] = ContentAction::parse($name) ?? [null, null];

			$capabilities[] = [
				'name'  => $name,
				'label' => $label,
				'group' => $this->capabilities->group($name),
				...($action === null ? [] : ['type' => $type, 'action' => $action->value])
			];
		}

		return $capabilities;
	}

	/**
	 * Describes the content types as a role's screen draws them: each its
	 * `name`, plural `label`, `kind`, whether it has `terms`, and `icon`.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function types(): array
	{
		return array_values(array_map(fn (ContentType $type): array => [
			'name'  => $type->name,
			'label' => $type->labels->plural,
			'kind'  => $type->kind()->value,
			'terms' => $this->types->classification($type->name) !== null,
			'icon'  => $type->icon
		], $this->types->all()));
	}

	/**
	 * Describes an account: its `username`, its `email` (D-370), its own
	 * `name` (or `null`), its `displayName` (D-370: the name, else the
	 * profile's title, else the username), its `avatar` (D-810), `roles`, `author` (the linked
	 * profile's slug, or `null`), its
	 * `profile` (D-353: `{"id", "handle", "slug", "title", "status",
	 * "url", "uses"}`, or `null`), `created` and `lastLogin`
	 * (Unix times), `status`, its password `link` (`expires`, and whether
	 * it has `expired`; `null` for none), whether it's `tokenOnly`
	 * (D-744), whether the viewer `manages` it, and, for your own, the
	 * new address waiting on its link (`emailChange`, D-849).
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
			'avatar'      => $this->avatar($account),
			'roles'       => $account->roles,
			'author'      => $this->accounts->slug($account),
			'profile'     => $this->profile($account),
			'created'     => $account->created,
			'lastLogin'   => $account->lastLogin,
			'status'      => $account->status()->value,
			'link'        => $link === null ? null : ['expires' => $link->expires, 'expired' => $link->expires <= $this->clock->now()->getTimestamp()],
			'tokenOnly'   => $account->tokenOnly,
			'manages'     => $this->rules->manages($viewer, $account),
			'emailChange' => $account->id === $viewer->id ? $this->emailChange($account) : null
		];
	}

	/**
	 * Returns an account's new email address waiting on its confirmation
	 * link (D-849), `{email, expires}`, or `null`. It's shown to the
	 * account alone, which is the only one that can ask for one.
	 *
	 * @return ?array{email: string, expires: int}
	 */
	public function emailChange(Account $account): ?array
	{
		try {
			$change = $this->changes->pending($account->id);
		} catch (CacheException) {
			return null;
		}

		return $change === null ? null : ['email' => $change->email, 'expires' => $change->expires];
	}

	/**
	 * Returns the URL of an account's picture (`Avatars`, D-810), or
	 * `null` for its initials, which the admin draws.
	 */
	public function avatar(Account $account): ?string
	{
		$image = $this->avatars->account($account)->image;

		return $image === '' ? null : $image;
	}

	/**
	 * Returns an account's profile, or `null` when it links to none or
	 * the profile is gone: its `id`, `handle`, `slug`, `title`,
	 * `status`, `url` on the site, and how many published entries credit
	 * it (`uses`).
	 *
	 * @return ?array{path: string, id: ?string, type: string, handle: ?string, slug: string, title: string, status: string, url: ?string, uses: int}
	 */
	public function profile(Account $account): ?array
	{
		$entry = $this->accounts->entry($account);

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
				fn (Account $account): array => ['username' => $account->username, 'displayName' => $this->accounts->displayName($account), 'avatar' => $this->avatar($account)],
				array_filter($accounts, static fn (Account $account): bool => in_array($role->name, $account->roles, true))
			)),
			'grantable'    => $grantable,
			'editable'     => $grantable && $origin->editable($role->name) && $this->permissions->can($viewer, Capability::RolesManage),
			...($origin === RoleOrigin::Changed && $builtIn !== null ? ['defaults' => $builtIn->capabilities()] : [])
		];
	}
}
