<?php

/**
 * Admin people controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\Accounts;
use Blush\Auth\AccountStore;
use Blush\Auth\AuthException;
use Blush\Auth\Capabilities;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Auth\Role;
use Blush\Auth\Roles;
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\Type\ContentTypes;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * Answers the admin's people screens (D-249), for accounts with
 * `accounts.manage`:
 *
 * - `GET roles`: every capability (`name`, `label`) and every role, as
 *   `PeopleJson::role()` describes it.
 * - `GET accounts`: every account, as `PeopleJson::account()` describes
 *   it, sorted by username.
 *
 * And for accounts with `accounts.manage` or `content.edit`:
 *
 * - `GET people` (D-329): one list of people, by name. Each is an author
 *   entry the viewer may edit, an author credited without one (with
 *   `content.edit.others`), or an account with no author entry listed
 *   (with `accounts.manage`): `{"name", "author"` (the slug, or `null`),
 *   `"entry"` (`{"id", "handle", "status"}`, or `null`), `"virtual"`
 *   (credited without a file), `"account"` (as `PeopleJson::account()`
 *   has it, or `null`: a guest, or an account the viewer can't see),
 *   and `"uses"` (how many published entries credit them)`}`.
 *
 * Changes go through `AccountEditController` and `RoleEditController`
 * (D-312).
 */
final readonly class PeopleController
{
	public function __construct(
		private Roles $roles,
		private Capabilities $capabilities,
		private AccountStore $accounts,
		private Permissions $permissions,
		private PeopleJson $json,
		private ContentRepository $content,
		private ContentTypes $types,
		private EntryHandles $handles,
		private Accounts $names
	) {}

	public function roles(ServerRequestInterface $request): ResponseInterface
	{
		$viewer = $this->viewer($request);

		if ($viewer === null) {
			return self::forbidden();
		}

		try {
			$accounts = $this->accounts->all();
		} catch (AuthException $error) {
			return self::damaged($error);
		}

		$capabilities = [];

		foreach ($this->capabilities->all() as $name => $label) {
			$capabilities[] = ['name' => $name, 'label' => $label];
		}

		return Response::json([
			'capabilities' => $capabilities,
			'roles'        => array_values(array_map(fn (Role $role): array => $this->json->role($role, $this->roles, $accounts, $viewer), $this->roles->all())),
			'all'          => Role::ALL
		], headers: ['Cache-Control' => 'no-store']);
	}

	public function accounts(ServerRequestInterface $request): ResponseInterface
	{
		$viewer = $this->viewer($request);

		if ($viewer === null) {
			return self::forbidden();
		}

		try {
			$all = $this->accounts->all();
		} catch (AuthException $error) {
			return self::damaged($error);
		}

		return Response::json(['accounts' => array_map(fn (Account $account): array => $this->json->account($account, $viewer), $all)], headers: ['Cache-Control' => 'no-store']);
	}

	public function people(ServerRequestInterface $request): ResponseInterface
	{
		$viewer  = $request->getAttribute(Account::class);
		$manages = $viewer instanceof Account && $this->permissions->can($viewer, Capability::AccountsManage);
		$edits   = $viewer instanceof Account && $this->permissions->can($viewer, Capability::ContentEdit);

		if (! $viewer instanceof Account || (! $manages && ! $edits)) {
			return Response::json(['error' => 'You aren\'t allowed to see the people here.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
		}

		try {
			$accounts = $manages ? $this->accounts->all() : [];
		} catch (AuthException $error) {
			return self::damaged($error);
		}

		$linked = [];

		foreach ($accounts as $account) {
			if ($account->author !== null) {
				$linked[$account->author] ??= $account;
			}
		}

		$authors = $this->types->authors();
		$counts  = $authors === null ? [] : $this->content->termCounts($authors->name);
		$people  = [];
		$placed  = [];

		if ($authors !== null && $edits) {
			$query = $this->permissions->restrict($viewer, Capability::ContentEdit, $this->content->query()->any()->type($authors->name)->limit(null));

			foreach ($query->get() as $entry) {
				$account             = $linked[$entry->key] ?? null;
				$people[$entry->key] = $this->person($entry->title !== '' ? $entry->title : $entry->key, $entry->key, $entry, $account, $viewer, $counts);

				if ($account !== null) {
					$placed[$account->username] = true;
				}
			}

			if ($this->permissions->can($viewer, Capability::ContentEditOthers)) {
				foreach (array_keys($counts) as $slug) {
					$slug = (string) $slug;

					if (! isset($people[$slug]) && $this->content->named($authors->name, $slug) === null) {
						$term          = $this->content->term($authors->name, $slug);
						$account       = $linked[$slug] ?? null;
						$people[$slug] = $this->person($term->title ?? $slug, $slug, null, $account, $viewer, $counts);

						if ($account !== null) {
							$placed[$account->username] = true;
						}
					}
				}
			}
		}

		// Accounts not listed with an author: those with none, those whose
		// author the viewer can't see, and a second account linked to one.
		foreach ($accounts as $account) {
			if (! isset($placed[$account->username])) {
				$entry    = $account->author === null || $authors === null ? null : $this->content->named($authors->name, $account->author);
				$people[] = $this->person($this->names->displayName($account), $account->author, $entry, $account, $viewer, $counts, virtual: false);
			}
		}

		$people = array_values($people);
		usort($people, static fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));

		return Response::json(['people' => $people], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Describes one person for `GET people`.
	 *
	 * @param  array<array-key, int> $counts
	 * @return array{name: string, author: ?string, entry: ?array{id: string, handle: ?string, status: string}, virtual: bool, account: ?array<string, mixed>, uses: int}
	 */
	private function person(string $name, ?string $author, ?Entry $entry, ?Account $account, Account $viewer, array $counts, ?bool $virtual = null): array
	{
		return [
			'name'    => $name,
			'author'  => $author,
			'entry'   => $entry === null ? null : ['id' => $entry->id, 'handle' => $this->handles->of($entry), 'status' => $entry->status->value],
			'virtual' => $virtual ?? ($entry === null && $author !== null),
			'account' => $account === null ? null : $this->json->account($account, $viewer),
			'uses'    => $author === null ? 0 : ($counts[$author] ?? 0)
		];
	}

	/**
	 * Returns the signed-in account when it may manage accounts.
	 */
	private function viewer(ServerRequestInterface $request): ?Account
	{
		$account = $request->getAttribute(Account::class);

		return $account instanceof Account && $this->permissions->can($account, Capability::AccountsManage) ? $account : null;
	}

	private static function damaged(AuthException $error): ResponseInterface
	{
		return Response::json(['error' => $error->getMessage()], Status::InternalServerError, ['Cache-Control' => 'no-store']);
	}

	private static function forbidden(): ResponseInterface
	{
		return Response::json(['error' => 'You aren\'t allowed to manage accounts.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
	}
}
