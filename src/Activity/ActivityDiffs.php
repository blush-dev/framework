<?php

/**
 * Activity diffs.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Activity;

use Psr\Clock\ClockInterface;
use Blush\Content\Entries;
use Blush\Content\Relation\Refs;
use Blush\Content\Writer\WriteException;
use Blush\Media\Index\MediaLibrary;
use Blush\Media\MediaException;
use Blush\Support\RequestMemo;

/**
 * What a change in the activity log changed (D-902), for its screen.
 *
 * The log keeps what a change replaced (`before`), not what it wrote, so
 * what it wrote is found where it went next. For an entry, `before` is
 * the whole of it, and what it wrote is what the next change to it
 * replaced, or, with no change since, the entry as it is now, when it's
 * still the version the change wrote. For a media file, `before` holds
 * only the details it changed, and each one's after is what the next
 * change to that detail replaced, or its value now. Every write goes
 * through the editors (`EntryEditor`, `MediaEditor`), which log it, so
 * one change's before is the last one's after; a file changed by hand
 * between them is the one thing this can't see, and for an entry, a
 * version that doesn't match says so.
 */
final readonly class ActivityDiffs
{
	/**
	 * Front matter every save keeps up, left out: when it was updated, as
	 * the editor's summaries leave it out, and the ids relations link to
	 * (`refs`), which the relations' own keys already show.
	 */
	private const array KEPT_UP = ['updated', Refs::FIELD];

	public function __construct(
		private ActivityLog $log,
		private Entries $content,
		private MediaLibrary $library,
		private ClockInterface $clock,
		private RequestMemo $memo = new RequestMemo()
	) {}

	/**
	 * Returns what a change changed.
	 *
	 * @throws ActivityException When the log can't be read.
	 */
	public function diff(Activity $activity): ActivityDiff
	{
		return $this->memo->remember("activity-diff|{$activity->id}", function () use ($activity): ActivityDiff {
			if (! $activity->action->isUndoable()) {
				return ActivityDiff::none(DiffState::New);
			}

			if ($activity->before === null) {
				return ActivityDiff::none($activity->time < $this->clock->now()->getTimestamp() - ActivityLog::KEEP_BEFORE ? DiffState::Expired : DiffState::NotKept);
			}

			return $activity->action->isEntry() ? $this->entry($activity, $activity->before) : $this->media($activity, $activity->before);
		});
	}

	/**
	 * An entry's change: its front matter, body, and key before and after.
	 *
	 * @param  array<string, mixed> $before
	 * @throws ActivityException
	 */
	private function entry(Activity $activity, array $before): ActivityDiff
	{
		$after = $this->entryAfter($activity);

		if ($after === null) {
			return ActivityDiff::none(DiffState::Unknown);
		}

		$was = self::frontMatter($before);
		$now = self::frontMatter($after);

		$fields = [];

		foreach (array_diff(array_unique([...array_keys($was), ...array_keys($now)]), self::KEPT_UP) as $name) {
			$old = $was[$name] ?? null;
			$new = $now[$name] ?? null;

			if ($old !== $new) {
				$fields[] = new FieldChange((string) $name, $old, $new);
			}
		}

		$bodyBefore = is_string($before['body'] ?? null) ? $before['body'] : '';
		$bodyAfter  = is_string($after['body'] ?? null) ? $after['body'] : '';
		$same       = $bodyBefore === $bodyAfter;

		return new ActivityDiff(
			DiffState::Shown,
			$fields,
			$same ? null : $bodyBefore,
			$same ? null : $bodyAfter,
			is_string($before['key'] ?? null) ? $before['key'] : null,
			is_string($after['key'] ?? null) ? $after['key'] : null
		);
	}

	/**
	 * What an entry's change wrote: what the next change to it replaced,
	 * or the entry as it is now when it's the version the change wrote;
	 * `null` when that can't be told.
	 *
	 * @return ?array<string, mixed>
	 * @throws ActivityException
	 */
	private function entryAfter(Activity $activity): ?array
	{
		$next = $this->log->later($activity, 1)[0] ?? null;

		if ($next !== null) {
			return $next->before;
		}

		$entry = $this->content->find($activity->subject);

		if ($entry === null) {
			return null;
		}

		try {
			$file = $this->content->editable($entry);
		} catch (WriteException) {
			return null;
		}

		return $activity->revision === null || $file->version !== $activity->revision
			? null
			: ['key' => $entry->key, 'frontMatter' => $file->frontMatter, 'body' => $file->body];
	}

	/**
	 * A media file's change: each detail it changed, before and after.
	 *
	 * @param  array<string, mixed> $before
	 * @throws ActivityException
	 */
	private function media(Activity $activity, array $before): ActivityDiff
	{
		$values = is_array($before['values'] ?? null) ? $before['values'] : [];
		$later  = $this->log->later($activity);

		try {
			$now = $this->library->findId($activity->subject)?->metadata()->values;
		} catch (MediaException) {
			$now = null;
		}

		$fields = [];

		foreach ($values as $name => $old) {
			$name = (string) $name;
			$new  = self::replacedLater($later, $name) ?? ($now === null ? null : [$now[$name] ?? null]);

			if ($new === null) {
				return ActivityDiff::none(DiffState::Unknown);
			}

			if ($old !== $new[0]) {
				$fields[] = new FieldChange($name, $old, $new[0]);
			}
		}

		return new ActivityDiff(DiffState::Shown, $fields);
	}

	/**
	 * The value a later change to a media file replaced in one of its
	 * details, wrapped so `null` (it had none) is a value, or `null` when
	 * no later change touched it.
	 *
	 * @param  list<Activity> $later
	 * @return ?array{mixed}
	 */
	private static function replacedLater(array $later, string $name): ?array
	{
		foreach ($later as $change) {
			$values = $change->before['values'] ?? null;

			if (is_array($values) && array_key_exists($name, $values)) {
				return [$values[$name]];
			}
		}

		return null;
	}

	/**
	 * An entry's front matter, from what the log keeps of it.
	 *
	 * @param  array<string, mixed> $state
	 * @return array<array-key, mixed>
	 */
	private static function frontMatter(array $state): array
	{
		return is_array($state['frontMatter'] ?? null) ? $state['frontMatter'] : [];
	}
}
