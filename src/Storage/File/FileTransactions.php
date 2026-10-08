<?php

/**
 * File transactions.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\File;

use Closure;
use Throwable;
use Blush\Core\Paths;
use Blush\Storage\StorageException;
use Blush\Support\Filesystem;

/**
 * Transactions over files, for the filesystem driver's stores
 * (`FileDataStore`, `FileRecordStore`; D-642, D-643), which share one,
 * so a transaction in one holds the other's writes too.
 *
 * A transaction takes `storage/cache/data.lock`, so none interleave.
 * Before a store first writes a file in one, it says so (`remember()`),
 * and the file's text is kept; when the transaction throws, every file
 * it wrote is put back as it was, the last first. One inside another is
 * part of it, but puts back its own writes when it fails, so the outer
 * one can go on without them.
 */
final class FileTransactions
{
	/**
	 * The files each open transaction wrote, outermost first, with their
	 * text before it (`null` for none).
	 *
	 * @var list<array<string, ?string>>
	 */
	private array $open = [];

	/**
	 * The lock, while a transaction runs.
	 *
	 * @var ?resource
	 */
	private mixed $lock = null;

	public function __construct(
		private readonly Paths $paths,
		private readonly Filesystem $filesystem
	) {}

	/**
	 * Runs `$write` in a transaction and returns what it returns.
	 *
	 * @template T
	 * @param  Closure(): T $write
	 * @return T
	 * @throws StorageException When the lock can't be taken.
	 */
	public function run(Closure $write): mixed
	{
		$outer = $this->open === [];

		if ($outer) {
			$this->acquire();
		}

		$this->open[] = [];

		try {
			$result = $write();
		} catch (Throwable $error) {
			$this->restore((array) array_pop($this->open));

			if ($outer) {
				$this->release();
			}

			throw $error;
		}

		$written = (array) array_pop($this->open);

		if ($outer) {
			$this->release();
		} else {
			// The outer transaction keeps the text from before either wrote.
			$this->open[] = (array) array_pop($this->open) + $written;
		}

		return $result;
	}

	/**
	 * Keeps a file's text from before the open transaction first writes
	 * it. Outside a transaction, it's nothing.
	 */
	public function remember(string $path): void
	{
		$last = array_key_last($this->open);

		if ($last === null || array_key_exists($path, $this->open[$last])) {
			return;
		}

		$text = is_file($path) ? @file_get_contents($path) : false;

		$this->open[$last][$path] = $text === false ? null : $text;
	}

	/**
	 * Puts files back as they were, the last written first.
	 *
	 * @param array<string, ?string> $written
	 */
	private function restore(array $written): void
	{
		foreach (array_reverse($written, true) as $path => $text) {
			try {
				if ($text === null) {
					@unlink($path);
				} else {
					$this->filesystem->writeAtomic($path, $text);
				}
			} catch (Throwable) {
				// The error that started this goes on; one file left is all this can't fix.
			}
		}
	}

	/**
	 * Takes the lock.
	 *
	 * @throws StorageException
	 */
	private function acquire(): void
	{
		if (! is_dir($this->paths->cache)) {
			@mkdir($this->paths->cache, 0775, true);
		}

		$lock = @fopen("{$this->paths->cache}/data.lock", 'c');

		if ($lock === false || ! flock($lock, LOCK_EX)) {
			throw new StorageException('Unable to take the data lock.');
		}

		$this->lock = $lock;
	}

	/**
	 * Lets the lock go.
	 */
	private function release(): void
	{
		if (is_resource($this->lock)) {
			flock($this->lock, LOCK_UN);
			fclose($this->lock);
		}

		$this->lock = null;
	}
}
