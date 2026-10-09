<?php

/**
 * Record session store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Session;

use Override;
use Psr\Clock\ClockInterface;
use Blush\Storage\Record\Operator;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageArea;

/**
 * Keeps sessions as records inside a database driver (D-645, D-662):
 * the `sessions` table, keyed by a hash of the session's id (never the id
 * itself, as files are named), with when it was last written, which
 * `prune()` goes by.
 */
final readonly class RecordSessionStore implements SessionStore
{
	/**
	 * The table's name.
	 */
	public const string TABLE = 'sessions';

	public function __construct(
		private RecordStores $stores,
		private ClockInterface $clock
	) {}

	/**
	 * The sessions' table.
	 */
	public static function table(): Table
	{
		return new Table(self::TABLE, StorageArea::Sessions, key: 'key', fields: ['key', 'written']);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function read(string $id): ?array
	{
		$fields = $this->record($id)->fields ?? null;

		if (! is_array($fields) || ! is_int($fields['created'] ?? null) || ! is_int($fields['lastSeen'] ?? null) || ! is_array($fields['data'] ?? null)) {
			return null;
		}

		/** @var array<string, mixed> $data */
		$data = $fields['data'];

		return ['created' => $fields['created'], 'lastSeen' => $fields['lastSeen'], 'data' => $data];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function write(string $id, array $record): void
	{
		$stored = $this->record($id) ?? Record::create($this->clock->now());

		$this->stores->store(self::table())->save(self::table(), $stored->withFields([
			...$record,
			'key'     => self::key($id),
			'written' => $this->clock->now()->getTimestamp()
		]));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function delete(string $id): void
	{
		$record = $this->record($id);

		if ($record !== null) {
			$this->stores->store(self::table())->delete(self::table(), $record->id);
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function prune(int $before): int
	{
		$table  = self::table();
		$store  = $this->stores->store($table);
		$pruned = 0;

		foreach ($this->stores->query($table)->where('written', Operator::Less, $before)->get() as $record) {
			$store->delete($table, $record->id);
			$pruned++;
		}

		return $pruned;
	}

	/**
	 * Returns a session's record, or `null`.
	 */
	private function record(string $id): ?Record
	{
		return $this->stores->store(self::table())->findByKey(self::table(), self::key($id));
	}

	/**
	 * Returns the key a session is kept under: a hash of its id.
	 */
	private static function key(string $id): string
	{
		return hash('sha256', $id);
	}
}
