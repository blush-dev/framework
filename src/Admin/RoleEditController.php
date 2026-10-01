<?php

/**
 * Admin role editing controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\AccountStore;
use Blush\Auth\AuthException;
use Blush\Auth\BuiltInRole;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Auth\Role;
use Blush\Auth\RoleEditor;
use Blush\Auth\Roles;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * Makes and changes roles from the admin (D-312), for accounts with
 * `accounts.manage`, through `RoleEditor` and within `PeopleRules` (you
 * can't grant a capability you don't have, and someone must still be
 * able to manage accounts):
 *
 * - `POST roles`: `{"name", "label", "description", "capabilities"}`
 *   makes a custom role (`201`), not named `new` (the admin's screen for
 *   making one).
 * - `PATCH roles/{name}`: any of `label`, `description`, and
 *   `capabilities` for a custom role; `capabilities` for a built-in
 *   other than the administrator.
 * - `DELETE roles/{name}`: deletes a custom role no account holds, or
 *   resets a changed built-in.
 *
 * Each answers with the `role` as it is now (`null` once deleted).
 * Refusals are `403`, `404`, or `422` with an `error` and maybe a
 * `field`.
 */
final readonly class RoleEditController
{
	public function __construct(
		private RoleEditor $editor,
		private Roles $roles,
		private AccountStore $accounts,
		private Permissions $permissions,
		private PeopleRules $rules,
		private PeopleJson $json
	) {}

	public function create(ServerRequestInterface $request): ResponseInterface
	{
		$actor = $this->manager($request);

		if ($actor === null) {
			return self::forbidden();
		}

		$input        = self::input($request);
		$name         = is_string($input['name'] ?? null) ? trim($input['name']) : '';
		$label        = is_string($input['label'] ?? null) ? $input['label'] : '';
		$description  = is_string($input['description'] ?? null) ? $input['description'] : '';
		$capabilities = self::strings($input['capabilities'] ?? []);

		if ($capabilities === null) {
			return self::error('Send "capabilities" as a list of names.', Status::BadRequest);
		}

		if (preg_match('/^[a-z][a-z0-9_-]*$/', $name) !== 1) {
			return self::error('Use lowercase letters, digits, "_", and "-" for the key, starting with a letter.', field: 'name');
		}

		// The admin's New Role screen is at `roles/new`.
		if ($name === 'new') {
			return self::error('"new" can\'t be a role\'s key here; pick another.', field: 'name');
		}

		if (! $this->rules->mayGrantAll($actor, $capabilities)) {
			return self::error('You can\'t make a role that can do things you can\'t.', Status::Forbidden, 'capabilities');
		}

		return $this->change($actor, $name, fn (): Roles => $this->editor->create($name, $label, $description, $capabilities), Status::Created);
	}

	public function update(ServerRequestInterface $request, string $name): ResponseInterface
	{
		$actor = $this->manager($request);

		if ($actor === null) {
			return self::forbidden();
		}

		$refusal = $this->check($actor, $name);

		if ($refusal !== null) {
			return $refusal;
		}

		$input        = self::input($request);
		$label        = $input['label'] ?? null;
		$description  = $input['description'] ?? null;
		$capabilities = array_key_exists('capabilities', $input) ? self::strings($input['capabilities']) : [];

		if (($label !== null && ! is_string($label)) || ($description !== null && ! is_string($description)) || $capabilities === null) {
			return self::error('Send any of a "label", a "description", and a list of "capabilities".', Status::BadRequest);
		}

		$capabilities = array_key_exists('capabilities', $input) ? $capabilities : null;

		if ($capabilities !== null && ! $this->rules->mayGrantAll($actor, $capabilities)) {
			return self::error('You can\'t give a role a capability you don\'t have.', Status::Forbidden, 'capabilities');
		}

		return $this->change($actor, $name, fn (): Roles => $this->editor->update($name, $label, $description, $capabilities));
	}

	public function delete(ServerRequestInterface $request, string $name): ResponseInterface
	{
		$actor = $this->manager($request);

		if ($actor === null) {
			return self::forbidden();
		}

		$refusal = $this->check($actor, $name);

		if ($refusal !== null) {
			return $refusal;
		}

		$defaults = BuiltInRole::tryFrom($name)?->role();

		if ($defaults !== null && ! $this->rules->mayGrant($actor, $defaults)) {
			return self::error(sprintf('The %s role can do things you can\'t as it was built, so you can\'t reset it.', $defaults->label), Status::Forbidden);
		}

		return $this->change($actor, $name, fn (): Roles => $this->editor->delete($name));
	}

	/**
	 * Checks that a role exists and the actor may change it. Returns the
	 * refusal, or `null`.
	 */
	private function check(Account $actor, string $name): ?ResponseInterface
	{
		$role = $this->roles->get($name);

		return match (true) {
			$role === null                         => self::error(sprintf('There\'s no "%s" role.', $name), Status::NotFound),
			! $this->rules->mayGrant($actor, $role) => self::error(sprintf('The %s role can do things you can\'t, so you can\'t change it.', $role->label), Status::Forbidden),
			default                                => null
		};
	}

	/**
	 * Makes a change, then puts the stored roles back if no account that
	 * isn't suspended could manage accounts any more.
	 *
	 * @param callable(): Roles $change
	 */
	private function change(Account $actor, string $name, callable $change, Status $status = Status::Ok): ResponseInterface
	{
		try {
			$before   = $this->editor->stored();
			$roles    = $change();
			$accounts = $this->accounts->all();
		} catch (AuthException $e) {
			return self::error($e->getMessage());
		}

		if (! $this->rules->keepsManager($accounts, $roles)) {
			$this->editor->restore($before);

			return self::error('That would leave no account that can manage accounts.', field: 'capabilities');
		}

		$role = $roles->get($name);

		return self::json(['role' => $role === null ? null : $this->json->role($role, $roles, $accounts, $actor)], $status);
	}

	/**
	 * Returns the signed-in account when it may manage accounts.
	 */
	private function manager(ServerRequestInterface $request): ?Account
	{
		$account = $request->getAttribute(Account::class);

		return $account instanceof Account && $this->permissions->can($account, Capability::AccountsManage) ? $account : null;
	}

	/**
	 * Returns a list of strings, or `null` when it isn't one.
	 *
	 * @return ?list<string>
	 */
	private static function strings(mixed $value): ?array
	{
		if (! is_array($value) || ! array_is_list($value)) {
			return null;
		}

		$strings = [];

		foreach ($value as $item) {
			if (! is_string($item) || $item === Role::ALL) {
				return null;
			}

			$strings[] = $item;
		}

		return array_values(array_unique($strings));
	}

	/**
	 * @return array<mixed>
	 */
	private static function input(ServerRequestInterface $request): array
	{
		try {
			$input = json_decode((string) $request->getBody(), true, 8, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return [];
		}

		return is_array($input) ? $input : [];
	}

	private static function forbidden(): ResponseInterface
	{
		return self::error('You aren\'t allowed to manage accounts.', Status::Forbidden);
	}

	private static function error(string $message, Status $status = Status::UnprocessableContent, ?string $field = null): ResponseInterface
	{
		return self::json(['error' => $message, ...($field === null ? [] : ['field' => $field])], $status);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, Status $status = Status::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}
}
