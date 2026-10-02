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
 * nothing. What it may do to entries is per content type (D-359): a
 * `ContentAction` on a type needs `content.{type}.{action}` (or
 * `content.*.{action}`). With an entry, two more rules apply:
 *
 * - **Ownership:** an entry is the account's own when it credits the
 *   account's author (its `authors`), and so is that author's own entry,
 *   the account's public name and bio. For any other entry, the account
 *   also needs the action's `.others` form (`content.post.edit.others`).
 *   An account with no author owns nothing.
 * - **Live entries:** editing or deleting an entry that isn't a draft
 *   changes the live site, so it also needs publishing for that entry.
 *   That's what keeps contributors to drafts.
 *
 * Both rules come down to which statuses an account may act on in a type,
 * for its own entries and for others' (`statuses()`). `can()` checks one
 * entry against them, and `restrict()` turns them into query conditions,
 * so a list of entries is filtered and paged in the index (D-230).
 */
final readonly class Permissions
{
	public function __construct(
		private Roles $roles,
		private Capabilities $capabilities,
		private ContentTypes $types
	) {}

	/**
	 * Whether the account may use a capability. A content action is
	 * checked on an entry, on a type by name, or, with neither, on any
	 * type; so is a content capability's name, on an entry.
	 */
	public function can(Account $account, string|Capability|ContentAction $capability, Entry|string|null $on = null): bool
	{
		if (is_string($capability) && $on instanceof Entry) {
			[$type, $action] = ContentAction::parse($capability) ?? [null, null];

			if ($action !== null && $type === $on->type->name) {
				$capability = $action;
			}
		}

		if ($capability instanceof ContentAction) {
			return match (true) {
				$on instanceof Entry => in_array($on->status, $this->statuses($account, $on->type->name, $capability, others: $capability->isOthers() || ! $this->owns($account, $on)), true),
				is_string($on)       => $this->statuses($account, $on, $capability, others: $capability->isOthers()) !== [],
				default              => array_any(array_keys($this->types->all()), fn (string $type): bool => $this->statuses($account, $type, $capability, others: $capability->isOthers()) !== [])
			};
		}

		return $this->grants($account, $capability instanceof Capability ? $capability->value : $capability);
	}

	/**
	 * Returns a copy of a query limited to the entries the account may use
	 * a content action on, by the same rules as `can()`, in each of the
	 * query's types (or every type): others' entries with the statuses
	 * `statuses()` allows, or the account's own (entries with its
	 * author's term) with theirs. An account that may act on every entry
	 * gets the query back as it was, and one that may act on none gets a
	 * query that finds nothing.
	 */
	public function restrict(Account $account, ContentAction $action, Query $query): Query
	{
		$types        = $query->types === [] ? array_keys($this->types->all()) : $query->types;
		$authors      = $this->types->profiles()?->name;
		$author       = $account->author;
		$alternatives = [];
		$everything   = true;

		foreach ($types as $type) {
			$others = $this->statuses($account, $type, $action, others: true);
			$own    = $this->statuses($account, $type, $action, others: false);

			$everything = $everything && $others === Status::cases();

			if ($others !== []) {
				$alternatives[] = static fn (Query $condition): Query => $condition->type($type)->status(...$others);
			}

			if ($own !== [] && $author !== null && $authors !== null) {
				$alternatives[] = static fn (Query $condition): Query => $condition->type($type)->status(...$own)->whereTerm($authors, $author);

				if ($type === $authors) {
					$alternatives[] = static fn (Query $condition): Query => $condition->type($type)->status(...$own)->names($author);
				}
			}
		}

		return $everything ? $query : $query->either(...$alternatives);
	}

	/**
	 * Returns the statuses of a type's entries the account may use a
	 * content action on: its own entries', or others'. Others' need the
	 * action's `.others` form. When acting changes the live site (editing
	 * or deleting), entries that aren't drafts also need publishing for
	 * them.
	 *
	 * @return list<Status>
	 */
	private function statuses(Account $account, string $type, ContentAction $action, bool $others): array
	{
		$base = $action->base();

		if (! $this->grants($account, $base->on($type)) || ($others && ! $this->grants($account, ($base->others() ?? $base)->on($type)))) {
			return [];
		}

		$changesLive = $base === ContentAction::Edit || $base === ContentAction::Delete;

		return ! $changesLive || $this->statuses($account, $type, ContentAction::Publish, $others) !== []
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
