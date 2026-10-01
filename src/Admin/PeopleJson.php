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
use Blush\Auth\AuthConfig;
use Blush\Auth\BuiltInRole;
use Blush\Auth\Role;
use Blush\Auth\RoleOrigin;
use Blush\Auth\Roles;
use Blush\Content\ContentRepository;

/**
 * Describes accounts and roles for the admin's people screens (D-249,
 * D-312), as the account looking at them may act on them. Password
 * hashes, link hashes, and preferences stay on the server.
 */
final readonly class PeopleJson
{
	public function __construct(
		private PeopleRules $rules,
		private ContentRepository $content,
		private AuthConfig $config,
		private ClockInterface $clock
	) {}

	/**
	 * Describes an account: its `username`, `name` (its author page's
	 * title, or `null`), `roles`, `author`, `created` and `lastLogin`
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
			'username'  => $account->username,
			'name'      => $account->author === null ? null : $this->content->named($this->config->authorTaxonomy, $account->author)?->title,
			'roles'     => $account->roles,
			'author'    => $account->author,
			'created'   => $account->created,
			'lastLogin' => $account->lastLogin,
			'status'    => $account->status()->value,
			'link'      => $link === null ? null : ['expires' => $link->expires, 'expired' => $link->expires <= $this->clock->now()->getTimestamp()],
			'manages'   => $this->rules->manages($viewer, $account)
		];
	}

	/**
	 * Describes a role: its `name`, `label`, `description`,
	 * `capabilities` (`*` for all), whether it's `builtIn`, its `origin`,
	 * the `accounts` that hold it, whether the viewer may give it to
	 * accounts (`grantable`), and whether the viewer may change it
	 * (`editable`). A changed built-in also has its `defaults`.
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
				static fn (Account $account): string => $account->username,
				array_filter($accounts, static fn (Account $account): bool => in_array($role->name, $account->roles, true))
			)),
			'grantable'    => $grantable,
			'editable'     => $grantable && $origin->editable($role->name),
			...($origin === RoleOrigin::Changed && $builtIn !== null ? ['defaults' => $builtIn->capabilities()] : [])
		];
	}
}
