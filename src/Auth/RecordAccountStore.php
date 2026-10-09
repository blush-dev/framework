<?php

/**
 * Record account store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

use Override;
use Psr\Clock\ClockInterface;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordException;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageArea;
use Blush\Storage\StorageException;

/**
 * Keeps accounts as records (D-662), for a driver that keeps the
 * accounts area as records (the `sqlite` driver): the `accounts` table,
 * keyed by username, each as `Account::toArray()` writes it. A plain
 * keyed table for now, until accounts have a repository on records (the
 * data layer's step 6).
 */
final readonly class RecordAccountStore implements AccountStore
{
	/**
	 * The table's name.
	 */
	public const string TABLE = 'accounts';

	public function __construct(
		private RecordStores $stores,
		private ClockInterface $clock
	) {}

	/**
	 * The accounts' table.
	 */
	public static function table(): Table
	{
		return new Table(self::TABLE, StorageArea::Accounts, key: 'username', fields: ['username']);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function find(string $username): ?Account
	{
		if (! Account::isValidUsername($username)) {
			return null;
		}

		$record = $this->record($username);

		return $record === null ? null : self::account($record);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function all(): array
	{
		$accounts = array_map(self::account(...), $this->records());

		usort($accounts, static fn (Account $a, Account $b): int => strcmp($a->username, $b->username));

		return $accounts;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function isEmpty(): bool
	{
		try {
			return $this->stores->store(self::table())->count(self::table(), new RecordQuery()) === 0;
		} catch (RecordException | StorageException $e) {
			throw new AuthException(sprintf('The accounts can\'t be read: %s', $e->getMessage()), previous: $e);
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function save(Account $account): void
	{
		$record = $this->record($account->username) ?? Record::create($this->clock->now());

		try {
			$this->stores->store(self::table())->save(self::table(), $record->withFields($account->toArray()));
		} catch (RecordException | StorageException $e) {
			throw new AuthException(sprintf('The account "%s" couldn\'t be saved: %s', $account->username, $e->getMessage()), previous: $e);
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function delete(string $username): void
	{
		$record = Account::isValidUsername($username) ? $this->record($username) : null;

		if ($record === null) {
			return;
		}

		try {
			$this->stores->store(self::table())->delete(self::table(), $record->id);
		} catch (RecordException | StorageException $e) {
			throw new AuthException(sprintf('The account "%s" couldn\'t be deleted: %s', $username, $e->getMessage()), previous: $e);
		}
	}

	/**
	 * Returns an account's record, or `null`.
	 *
	 * @throws AuthException When the store can't be read.
	 */
	private function record(string $username): ?Record
	{
		try {
			return $this->stores->store(self::table())->findByKey(self::table(), $username);
		} catch (RecordException | StorageException $e) {
			throw new AuthException(sprintf('The account "%s" can\'t be read: %s', $username, $e->getMessage()), previous: $e);
		}
	}

	/**
	 * Returns every account's record.
	 *
	 * @return list<Record>
	 * @throws AuthException When the store can't be read.
	 */
	private function records(): array
	{
		try {
			return $this->stores->query(self::table())->get()->records;
		} catch (RecordException | StorageException $e) {
			throw new AuthException(sprintf('The accounts can\'t be read: %s', $e->getMessage()), previous: $e);
		}
	}

	/**
	 * Returns a record's account.
	 *
	 * @throws AuthException When it isn't one.
	 */
	private static function account(Record $record): Account
	{
		return Account::fromArray($record->fields);
	}
}
