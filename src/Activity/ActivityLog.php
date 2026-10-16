<?php

/**
 * Activity log.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Activity;

use Closure;
use Psr\Clock\ClockInterface;
use Blush\Storage\Record\Order;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordException;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordStore;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageArea;
use Blush\Storage\StorageException;
use Blush\Support\Uuid;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * The activity log (D-758): every change to entries and media, made in
 * the admin, by an app through the MCP server, on the command line, or
 * through the API, in the `activity` table, kept with accounts (on
 * files, `storage/activity/{id}.json`).
 *
 * The write paths record it (`EntryEditor`, `MediaEditor`), so there's
 * one log for every way in. A change that can be undone keeps what it
 * replaced for `KEEP_BEFORE`; the change itself is kept for `KEEP`; the
 * pruning job removes both when they're due.
 */
final readonly class ActivityLog
{
	/**
	 * The table's name.
	 */
	public const string TABLE = 'activity';

	/**
	 * How long, in seconds, a change is kept.
	 */
	public const int KEEP = 90 * 86400;

	/**
	 * How long, in seconds, what a change replaced is kept for Undo.
	 */
	public const int KEEP_BEFORE = 30 * 86400;

	public function __construct(
		private RecordStores|RecordStore $stores,
		private ClockInterface $clock,
		private Messages $messages
	) {}

	/**
	 * The log's table.
	 */
	public static function table(): Table
	{
		return new Table(self::TABLE, StorageArea::Accounts, fields: ['time', 'account', 'via', 'client', 'action', 'subject']);
	}

	/**
	 * Records a change, and returns it. A change that undoes another
	 * (`Actor::$undoes`) marks it undone. A summary written with `t()` is
	 * kept as its message, and shown in the reader's language (D-836).
	 *
	 * @param  ?array<string, mixed> $before What it replaced, for Undo.
	 * @throws ActivityException When it can't be written.
	 */
	public function record(Actor $actor, ActivityAction $action, string $subject, string $title, string|Text $summary, ?array $before = null, ?string $revision = null): Activity
	{
		$now    = $this->clock->now();
		$record = Record::create($now);

		$activity = new Activity(
			id: $record->id,
			time: $now->getTimestamp(),
			account: $actor->account->id,
			via: $actor->via,
			action: $action,
			subject: $subject,
			title: $title,
			summary: $summary,
			client: $actor->client,
			token: $actor->token,
			before: $action->isUndoable() ? $before : null,
			revision: $revision,
			undoes: $actor->undoes
		);

		$this->save($activity);

		$undone = $actor->undoes === null ? null : $this->find($actor->undoes);

		if ($undone !== null) {
			$this->save($undone->withUndone($activity->id));
		}

		return $activity;
	}

	/**
	 * Returns a change by id, or `null`.
	 *
	 * @throws ActivityException When the log can't be read.
	 */
	public function find(string $id): ?Activity
	{
		$record = $this->read(fn (RecordStore $store): ?Record => Uuid::isValid($id) ? $store->find(self::table(), $id) : null);

		return $record === null ? null : $this->activity($record);
	}

	/**
	 * Returns a page of changes, newest first, and how many there are.
	 *
	 * @return array{list<Activity>, int}
	 * @throws ActivityException When the log can't be read.
	 */
	public function list(ActivityFilter $filter, int $perPage = 50, int $page = 1): array
	{
		$query = self::query($filter);

		return $this->read(fn (RecordStore $store): array => [
			array_map($this->activity(...), $store->select(self::table(), $query->orderBy('time', Order::Desc)->orderBy('id', Order::Desc)->paginate($perPage, $page))->records),
			$store->count(self::table(), $query)
		]);
	}

	/**
	 * Returns the names of the apps and tokens the log names, in order.
	 *
	 * @return list<string>
	 * @throws ActivityException When the log can't be read.
	 */
	public function clients(): array
	{
		$counts = $this->read(fn (RecordStore $store): array => $store->countBy(self::table(), new RecordQuery(), 'client'));
		$names  = array_values(array_filter(array_map(static fn (array $count): string => (string) $count['value'], $counts), static fn (string $name): bool => $name !== ''));

		sort($names, SORT_NATURAL | SORT_FLAG_CASE);

		return $names;
	}

	/**
	 * Returns the changes made to a change's entry or file after it,
	 * oldest first, up to `$limit`.
	 *
	 * @return list<Activity>
	 * @throws ActivityException When the log can't be read.
	 */
	public function later(Activity $activity, int $limit = 100): array
	{
		// Ids are made from the time to the millisecond (UUIDv7), so they
		// order changes more finely than `time`, which is in seconds.
		$query = new RecordQuery()
			->where('subject', '=', $activity->subject)
			->where('time', '>=', $activity->time)
			->orderBy('id');

		$later = array_filter(
			array_map($this->activity(...), $this->read(fn (RecordStore $store): array => $store->select(self::table(), $query)->records)),
			static fn (Activity $other): bool => strcmp($other->id, $activity->id) > 0
		);

		return array_slice(array_values($later), 0, $limit);
	}

	/**
	 * Returns the latest change to an entry or file, or `null`.
	 *
	 * @throws ActivityException When the log can't be read.
	 */
	public function latest(string $subject): ?Activity
	{
		return $this->list(new ActivityFilter(subject: $subject), 1)[0][0] ?? null;
	}

	/**
	 * Removes changes older than `KEEP`, and what changes older than
	 * `KEEP_BEFORE` replaced, and returns how many of each.
	 *
	 * @return array{int, int}
	 * @throws ActivityException When the log can't be read or written.
	 */
	public function prune(): array
	{
		$now     = $this->clock->now()->getTimestamp();
		$removed = 0;
		$cleared = 0;

		$old = $this->read(fn (RecordStore $store): array => $store->select(self::table(), new RecordQuery()->where('time', '<', $now - self::KEEP_BEFORE))->records);

		foreach (array_map($this->activity(...), $old) as $activity) {
			if ($activity->time < $now - self::KEEP) {
				$this->write(fn (RecordStore $store): null => $store->delete(self::table(), $activity->id));
				$removed++;
			} elseif ($activity->before !== null) {
				$this->save($activity->withoutBefore());
				$cleared++;
			}
		}

		return [$removed, $cleared];
	}

	/**
	 * Returns the query a filter makes.
	 */
	private static function query(ActivityFilter $filter): RecordQuery
	{
		$query = new RecordQuery();
		$where = [
			'account' => $filter->account,
			'via'     => $filter->via?->value,
			'client'  => $filter->client,
			'action'  => $filter->action?->value,
			'subject' => $filter->subject
		];

		foreach ($where as $key => $value) {
			$query = $value === null ? $query : $query->where($key, '=', $value);
		}

		return $query;
	}

	/**
	 * Saves a change, and returns it.
	 *
	 * @throws ActivityException When it can't be written.
	 */
	private function save(Activity $activity): Activity
	{
		$this->write(fn (RecordStore $store): Record => $store->save(self::table(), new Record($activity->id, $activity->toArray())));

		return $activity;
	}

	/**
	 * Returns what reading the table gives.
	 *
	 * @template T
	 * @param  Closure(RecordStore): T $read
	 * @return T
	 * @throws ActivityException When it can't be read.
	 */
	private function read(Closure $read): mixed
	{
		try {
			return $read($this->store());
		} catch (RecordException | StorageException $e) {
			throw new ActivityException((string) $this->messages->t('activity.activity_log.unreadable', 'The activity log can\'t be read: {problem}', problem: $e->getMessage()), previous: $e);
		}
	}

	/**
	 * Runs a write to the table.
	 *
	 * @param  Closure(RecordStore): mixed $write
	 * @throws ActivityException When it can't be written.
	 */
	private function write(Closure $write): void
	{
		try {
			$write($this->store());
		} catch (RecordException | StorageException $e) {
			throw new ActivityException((string) $this->messages->t('activity.activity_log.unwritable', 'The activity log couldn\'t be written: {problem}', problem: $e->getMessage()), previous: $e);
		}
	}

	/**
	 * The store that keeps the table.
	 *
	 * @throws StorageException
	 */
	private function store(): RecordStore
	{
		return $this->stores instanceof RecordStores ? $this->stores->store(self::table()) : $this->stores;
	}

	/**
	 * Returns a record's change.
	 *
	 * @throws ActivityException When it isn't one.
	 */
	private function activity(Record $record): Activity
	{
		return Activity::fromArray($record->fields, $record->id, $this->messages);
	}
}
