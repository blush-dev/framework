<?php

/**
 * Status repair.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Workflow;

use Blush\Activity\Actor;
use Blush\Auth\Account;
use Blush\Content\Editing\EditRefused;
use Blush\Content\Editing\EntryEditor;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Writer\WriteException;

/**
 * Finds the entries whose stored status isn't in their type's workflow
 * (one a type moved away from, or one nothing registers now, a plugin
 * that's off), and moves them to the workflow's `initial` (D-853), for
 * Site Health's fix (`POST health/statuses`). Each move is
 * `EntryEditor::resetStatus()`'s.
 */
final readonly class StatusRepair
{
	public function __construct(
		private Entries $content,
		private Workflows $workflows,
		private EntryEditor $editor
	) {}

	/**
	 * Returns the entries to repair, by id: each its `type`, the `status`
	 * it's stored with, and the workflow's `initial` it would get.
	 *
	 * @return array<string, array{type: string, status: string, initial: string}>
	 */
	public function report(): array
	{
		$found = [];

		foreach ($this->content->query()->allStatuses()->anyLanguage()->limit(null)->get()->all() as $entry) {
			$stored   = $this->stored($entry);
			$workflow = $this->workflows->for($entry->type);

			if ($entry->id === null || $stored === null || $workflow->has($stored)) {
				continue;
			}

			$found[$entry->id] = ['type' => $entry->type->name, 'status' => $stored, 'initial' => $workflow->initial];
		}

		return $found;
	}

	/**
	 * Moves entries, by id, to their workflows' `initial`, for an
	 * account; returns the ids moved, and why each other couldn't be.
	 *
	 * @param  list<string> $ids
	 * @return array{changed: list<string>, failed: array<string, string>}
	 */
	public function repair(Account $account, array $ids): array
	{
		$changed = [];
		$failed  = [];

		foreach ($ids as $id) {
			$entry = $this->content->find($id);

			if ($entry === null) {
				$failed[$id] = 'There\'s no entry with that id.';

				continue;
			}

			try {
				$this->editor->resetStatus(Actor::admin($account), $entry);
				$changed[] = $id;
			} catch (EditRefused $e) {
				$failed[$id] = $e->getMessage();
			}
		}

		return ['changed' => $changed, 'failed' => $failed];
	}

	/**
	 * Returns the status an entry is stored with, or `null` for one in the
	 * trash. Only a scheduled or expired entry's file is read: its status
	 * now is worked out from a stored live one.
	 */
	private function stored(Entry $entry): ?string
	{
		if ($entry->status->is(Status::TRASH)) {
			return null;
		}

		if (! $entry->status->is(Status::SCHEDULED) && ! $entry->status->is(Status::EXPIRED)) {
			return $entry->status->name;
		}

		try {
			return Transitions::stored($this->content->editable($entry)->frontMatter);
		} catch (WriteException) {
			return null;
		}
	}
}
