<?php

/**
 * Permissions.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

use Blush\Content\Entry\Entry;
use Blush\Content\Status;

/**
 * Answers whether an account may do something (D-217). An account can do
 * what any of its roles allows; a role it names that doesn't exist grants
 * nothing. With an entry, two more rules apply:
 *
 * - **Ownership:** an entry is the account's own when it credits the
 *   account's author (its `authors`). For any other entry, the account
 *   also needs the capability's `.others` form (`content.edit.others`).
 *   An account with no author owns nothing.
 * - **Live entries:** editing or deleting an entry that isn't a draft
 *   changes the live site, so it also needs `content.publish` for that
 *   entry. That's what keeps contributors to drafts.
 */
final readonly class Permissions
{
	public function __construct(
		private Roles $roles,
		private Capabilities $capabilities,
		private AuthConfig $config
	) {}

	/**
	 * Whether the account may use a capability, on an entry if given.
	 */
	public function can(Account $account, string|Capability $capability, ?Entry $entry = null): bool
	{
		$capability = $capability instanceof Capability ? $capability->value : $capability;

		if (! $this->grants($account, $capability)) {
			return false;
		}

		if ($entry === null) {
			return true;
		}

		if (! $this->owns($account, $entry) && ! $this->grants($account, "{$capability}.others")) {
			return false;
		}

		$changesLive = in_array($capability, [Capability::ContentEdit->value, Capability::ContentDelete->value], true)
			&& $entry->status !== Status::Draft;

		return ! $changesLive || $this->can($account, Capability::ContentPublish, $entry);
	}

	/**
	 * Whether the entry credits the account's author.
	 */
	public function owns(Account $account, Entry $entry): bool
	{
		return $account->author !== null && $entry->hasTerm($this->config->authorTaxonomy, $account->author);
	}

	/**
	 * Returns every registered capability the account's roles grant (for
	 * the admin, which hides what the account can't do).
	 *
	 * @return list<string>
	 */
	public function capabilities(Account $account): array
	{
		return array_values(array_filter(
			array_keys($this->capabilities->all()),
			fn (string $capability): bool => $this->grants($account, $capability)
		));
	}

	/**
	 * Whether any of the account's roles grants a capability.
	 */
	private function grants(Account $account, string $capability): bool
	{
		return array_any($account->roles, fn (string $name): bool => $this->roles->get($name)?->allows($capability) ?? false);
	}
}
