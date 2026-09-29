<?php

/**
 * File account store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

use JsonException;
use Override;
use Blush\Core\Paths;
use Blush\Support\Filesystem;
use Blush\Support\FilesystemException;

/**
 * Keeps each account in `storage/accounts/{username}.json` (D-217),
 * readable only by its owner and group, since it holds a password hash.
 * Usernames are checked before they become file names.
 */
final readonly class FileAccountStore implements AccountStore
{
	public function __construct(
		private Paths $paths,
		private Filesystem $filesystem
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function find(string $username): ?Account
	{
		if (! Account::isValidUsername($username)) {
			return null;
		}

		$file     = "{$this->paths->accounts}/{$username}.json";
		$contents = @file_get_contents($file);

		if ($contents === false) {
			return null;
		}

		try {
			$data = json_decode($contents, true, 16, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new AuthException(sprintf('%s isn\'t valid JSON: %s', $this->paths->relative($file), $e->getMessage()), previous: $e);
		}

		$account = is_array($data) ? Account::fromArray($data) : throw new AuthException(sprintf('%s isn\'t an account.', $this->paths->relative($file)));

		return $account->username === $username
			? $account
			: throw new AuthException(sprintf('%s holds the account "%s".', $this->paths->relative($file), $account->username));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function all(): array
	{
		$accounts = [];

		foreach ($this->files() as $file) {
			$account = $this->find(basename($file, '.json'));

			if ($account !== null) {
				$accounts[] = $account;
			}
		}

		usort($accounts, static fn (Account $a, Account $b): int => strcmp($a->username, $b->username));

		return $accounts;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function isEmpty(): bool
	{
		return $this->files() === [];
	}

	/**
	 * @inheritDoc
	 * @throws FilesystemException When the file can't be written.
	 * @throws JsonException
	 */
	#[Override]
	public function save(Account $account): void
	{
		$this->filesystem->writeAtomic(
			"{$this->paths->accounts}/{$account->username}.json",
			json_encode($account->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n",
			0660
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function delete(string $username): void
	{
		if (Account::isValidUsername($username)) {
			@unlink("{$this->paths->accounts}/{$username}.json");
		}
	}

	/**
	 * Returns the account files.
	 *
	 * @return list<string>
	 */
	private function files(): array
	{
		return glob("{$this->paths->accounts}/*.json") ?: [];
	}
}
