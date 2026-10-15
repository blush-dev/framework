<?php

/**
 * Transitions.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Workflow;

use Stringable;
use Blush\Activity\Actor;
use Blush\Auth\Account;
use Blush\Auth\ContentAction;
use Blush\Auth\Permissions;
use Blush\Content\Entry\Entry;
use Blush\Content\Type\ContentType;
use Blush\Event\Dispatcher;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * What every status change goes through (D-735, D-851): which
 * transitions an account may make from an entry's status (`allowed()`),
 * whether it may move an entry to a status (`refusal()`), and the events
 * around the change (`changing()`, `changed()`). `EntryEditor`, the one
 * write path (D-756), asks it before a save that changes a status and
 * tells it after.
 *
 * Statuses here are as stored (`published`, never `scheduled`): a move
 * into a live status with a future date is scheduled, and changing only
 * the date isn't a transition.
 *
 * Moving an entry from one status to another needs:
 *
 * - **A transition between them** in its type's workflow. An entry
 *   whose status isn't in the workflow is offered the transitions from
 *   `initial`, and may be moved to `initial` itself (D-851).
 * - **The transition's capability** (`content.{type}.transition.{name}`),
 *   unless it has none of its own.
 * - **Publishing the entry,** into or out of a live status (D-734).
 *
 * Edit access to the entry is the caller's to check, as for any save. A
 * new entry, which has no entry yet, is checked on its type: it starts
 * at its workflow's `initial`, and a status other than that is a
 * transition from it.
 */
final readonly class Transitions
{
	/**
	 * The name of the move back to `initial` that an entry outside its
	 * workflow is offered.
	 */
	public const string RETURN = 'return';

	public function __construct(
		private Workflows $workflows,
		private Statuses $statuses,
		private Permissions $permissions,
		private Dispatcher $events,
		private Messages $messages
	) {}

	/**
	 * Returns a copy that checks with other permissions, such as a
	 * token's (D-744).
	 */
	public function withPermissions(Permissions $permissions): self
	{
		return clone($this, ['permissions' => $permissions]);
	}

	/**
	 * Returns the status an entry is stored with, from its front matter:
	 * the one it names, lowercased, or `published` for none.
	 *
	 * @param array<array-key, mixed> $frontMatter
	 */
	public static function stored(array $frontMatter): string
	{
		$status = $frontMatter['status'] ?? null;

		return is_string($status) && trim($status) !== '' ? strtolower(trim($status)) : Status::PUBLISHED;
	}

	/**
	 * Returns the transitions the account may make from an entry's (or a
	 * type's new one's) stored status, in its workflow's order, with the
	 * move back to `initial` last for an entry outside its workflow.
	 *
	 * @return list<Transition>
	 */
	public function allowed(Account $account, Entry|ContentType $on, string $from): array
	{
		return array_values(array_filter($this->offered($on instanceof Entry ? $on->type : $on, $from), fn (Transition $transition): bool => $this->refusal($account, $on, $from, $transition->to) === null));
	}

	/**
	 * Returns the transition that moves an entry (or a type's new one)
	 * from a stored status to another, or `null` when there's none, or the
	 * statuses are one.
	 */
	public function between(Entry|ContentType $on, string $from, string $to): ?Transition
	{
		$type = $on instanceof Entry ? $on->type : $on;

		return $from === $to ? null : array_find($this->offered($type, $from), static fn (Transition $transition): bool => $transition->to === $to);
	}

	/**
	 * Returns the transition by a name that the account may make from an
	 * entry's stored status, or `null`.
	 */
	public function named(Account $account, Entry $entry, string $from, string $name): ?Transition
	{
		return array_find($this->allowed($account, $entry, $from), static fn (Transition $transition): bool => $transition->name === $name);
	}

	/**
	 * Says why the account may not move an entry (or a type's new one)
	 * from a stored status to another, or returns `null` when it may (or
	 * the statuses are one). Without a transition between them, it's
	 * what's asked that's wrong (`isMissing()` says so); otherwise, it's
	 * who's asking.
	 */
	public function refusal(Account $account, Entry|ContentType $on, string $from, string $to): ?Text
	{
		if ($from === $to) {
			return null;
		}

		$type       = $on instanceof Entry ? $on->type : $on;
		$workflow   = $this->workflows->for($type);
		$transition = $this->between($type, $from, $to);

		if ($transition === null) {
			return $workflow->has($to)
				? $this->messages->t('workflow.transitions.no_way', 'There\'s no way for {items} to go from {from} to {to}.', items: $type->labels->items, from: $this->label($from), to: $this->label($to))
				: $this->messages->t('workflow.transitions.not_in_workflow', 'The status {to} isn\'t one {items} use; use one of: {statuses}.', items: $type->labels->items, to: $this->label($to), statuses: implode(', ', $workflow->statuses));
		}

		if ($transition->capability && ! $this->permissions->can($account, $transition->capabilityOn($type->name))) {
			return $this->messages->t('workflow.transitions.not_allowed', 'You aren\'t allowed to {transition} that entry.', transition: (string) $transition->label);
		}

		$live = $this->statuses->get($from)->live || $this->statuses->get($to)->live;

		if ($live && ! $this->permissions->can($account, ContentAction::Publish, $on instanceof Entry ? $on : $type->name)) {
			return $this->statuses->get($to)->live
				? $this->messages->t('workflow.transitions.cannot_publish', 'You aren\'t allowed to publish that entry.')
				: $this->messages->t('workflow.transitions.cannot_unpublish', 'You aren\'t allowed to take that entry off the site.');
		}

		return null;
	}

	/**
	 * Whether a move from a stored status to another has no transition
	 * in an entry's (or a type's) workflow, so it's refused whoever asks.
	 */
	public function isMissing(Entry|ContentType $on, string $from, string $to): bool
	{
		return $from !== $to && $this->between($on, $from, $to) === null;
	}

	/**
	 * Dispatches `StatusChanging` before a change is written, by a
	 * transition or (`null`) by Trash, Restore, or Undo, and returns why a
	 * listener refused it, or `null`.
	 */
	public function changing(Actor $actor, Entry $entry, string $from, string $to, ?Transition $transition): string|Stringable|null
	{
		if ($from === $to) {
			return null;
		}

		$event = new StatusChanging($actor, $entry, $from, $to, $transition);

		$this->events->dispatch($event);

		return $event->refusal;
	}

	/**
	 * Dispatches `StatusChanged` once a change is written.
	 */
	public function changed(Actor $actor, Entry $entry, string $from, string $to, ?Transition $transition): void
	{
		if ($from !== $to) {
			$this->events->dispatch(new StatusChanged($actor, $entry, $from, $to, $transition));
		}
	}

	/**
	 * Returns the transitions an entry's workflow offers from a stored
	 * status, with a move back to `initial` for one outside it (D-851).
	 *
	 * @return list<Transition>
	 */
	private function offered(ContentType $type, string $from): array
	{
		$workflow = $this->workflows->for($type);
		$offered  = $workflow->from($from);

		if (! $workflow->has($from) && $from !== $workflow->initial) {
			$offered[] = new Transition(
				self::RETURN,
				$this->messages->t('workflow.transitions.return', 'Move to {status}', status: $this->label($workflow->initial)),
				[$from],
				$workflow->initial,
				capability: false
			);
		}

		return $offered;
	}

	/**
	 * Returns a status's label.
	 */
	private function label(string $status): string
	{
		return (string) $this->statuses->get($status)->label;
	}
}
