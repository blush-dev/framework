<?php

/**
 * Admin links.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Blush\Auth\Account;
use Blush\Content\Entry\Entry;
use Blush\Core\AppConfig;

/**
 * Builds addresses of the admin's screens for the command line and the
 * admin: choosing a password with a one-time link (D-312), for
 * `account:add --link` (D-720), and editing an entry, for `content:new`
 * (D-721).
 */
final readonly class AdminLinks
{
	public function __construct(
		private AdminConfig $admin,
		private AppConfig $app
	) {}

	/**
	 * Returns the address that sets an account's password with a token.
	 * The account and token are in the fragment, so they never reach a
	 * server's logs.
	 */
	public function setPassword(Account $account, string $token): string
	{
		$fragment = http_build_query(['account' => $account->username, 'token' => $token]);

		return $this->app->absoluteUrl("{$this->admin->path}/set-password#{$fragment}");
	}

	/**
	 * Returns the address of an entry's editor, at its type and id
	 * (D-253), or `null` for an entry without an id.
	 */
	public function entry(Entry $entry): ?string
	{
		return $entry->id === null ? null : $this->app->absoluteUrl("{$this->admin->path}/content/{$entry->type->name}/{$entry->id}");
	}
}
