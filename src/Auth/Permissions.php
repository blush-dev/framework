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
use Blush\Content\Query\Query;
use Blush\Content\Status;
use Blush\Content\Type\ContentTypes;

/**
 * Answers whether an account may do something (D-217). An account can do
 * what any of its roles allows; a role it names that doesn't exist grants
 * nothing. With an entry, two more rules apply:
 *
 * - **Ownership:** an entry is the account's own when it credits the
 *   account's author (its `authors`), and so is that author's own entry,
 *   the account's public name and bio. For any other entry, the account
 *   also needs the capability's `.others` form (`content.edit.others`).
 *   An account with no author owns nothing.
 * - **Live entries:** editing or deleting an entry that isn't a draft
 *   changes the live site, so it also needs `content.publish` for that
 *   entry. That's what keeps contributors to drafts.
 *
 * Both rules come down to which statuses an account may act on, for its
 * own entries and for others' (`statuses()`). `can()` checks one entry
 * against them, and `restrict()` turns them into query conditions, so a
 * list of entries is filtered and paged in the index (D-230).
 */
final readonly class Permissions
{
	public function __construct(
		private Roles $roles,
		private Capabilities $capabilities,
		private ContentTypes $types
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

		return $entry === null
			|| in_array($entry->status, $this->statuses($account, $capability, others: ! $this->owns($account, $entry)), true);
	}

	/**
	 * Returns a copy of a query limited to the entries the account may use
	 * a capability on, by the same rules as `can()`: others' entries with
	 * the statuses `statuses()` allows, or the account's own (entries with
	 * its author's term) with theirs. An account that may use it on
	 * every entry gets the query back as it was, and one that may use it
	 * on none gets a query that finds nothing.
	 */
	public function restrict(Account $account, string|Capability $capability, Query $query): Query
	{
		$capability   = $capability instanceof Capability ? $capability->value : $capability;
		$others       = $this->statuses($account, $capability, others: true);
		$own          = $this->statuses($account, $capability, others: false);
		$authors      = $this->types->profiles()?->name;
		$author       = $account->author;
		$alternatives = [];

		if ($others === Status::cases()) {
			return $query;
		}

		if ($others !== []) {
			$alternatives[] = static fn (Query $condition): Query => $condition->status(...$others);
		}

		if ($own !== [] && $author !== null && $authors !== null) {
			$alternatives[] = static fn (Query $condition): Query => $condition->status(...$own)->whereTerm($authors, $author);
			$alternatives[] = static fn (Query $condition): Query => $condition->status(...$own)->type($authors)->names($author);
		}

		return $query->either(...$alternatives);
	}

	/**
	 * Returns the statuses of entries the account may use a capability
	 * on: its own entries', or others'. Others' need the capability's
	 * `.others` form. When acting changes the live site (editing or
	 * deleting), entries that aren't drafts also need `content.publish`
	 * for them.
	 *
	 * @return list<Status>
	 */
	private function statuses(Account $account, string $capability, bool $others): array
	{
		if (! $this->grants($account, $capability) || ($others && ! $this->grants($account, "{$capability}.others"))) {
			return [];
		}

		$changesLive = in_array($capability, [Capability::ContentEdit->value, Capability::ContentDelete->value], true);

		return ! $changesLive || $this->statuses($account, Capability::ContentPublish->value, $others) !== []
			? Status::cases()
			: [Status::Draft];
	}

	/**
	 * Whether the entry credits the account's author, or is that author.
	 */
	public function owns(Account $account, Entry $entry): bool
	{
		$authors = $this->types->profiles()?->name;

		return $account->author !== null && $authors !== null && (
			$entry->hasTerm($authors, $account->author)
			|| ($entry->type->name === $authors && $entry->key === $account->author)
		);
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
