<?php

/**
 * File data store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Data;

use Closure;
use DirectoryIterator;
use JsonException;
use Override;
use Throwable;
use Blush\Core\Paths;
use Blush\Support\Filesystem;

/**
 * Keeps the data area as JSON files in `user/data` (D-631, D-642): the
 * record `types/post` is `user/data/types/post.json`.
 *
 * A file's `$schema` key, which points an editor at its JSON Schema, is
 * never part of a record (D-491), and saving keeps it first. A file that
 * can't be read is named by its path from the site's root. Writes are
 * atomic, and transactions take `storage/cache/data.lock`, keeping each
 * file's text as it was before its first write, to put back.
 */
final class FileDataStore implements DataStore
{
	/**
	 * The files written by each open transaction, outermost first, with
	 * their text before it (`null` for none).
	 *
	 * @var list<array<string, ?string>>
	 */
	private array $transactions = [];

	/**
	 * The open lock, while a transaction runs.
	 *
	 * @var ?resource
	 */
	private mixed $lock = null;

	public function __construct(
		private readonly Paths $paths,
		private readonly DataLoader $loader,
		private readonly Filesystem $filesystem
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function has(string $name): bool
	{
		return $this->loader->find($this->paths->data, $name) !== null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function load(string $name): ?array
	{
		$text = $this->text($this->loader->path($this->paths->data, $name));

		if ($text === null) {
			return null;
		}

		try {
			$data = DataLoader::parse($text);
		} catch (InvalidData $error) {
			throw InvalidData::inFile($this->location($name), $error);
		}

		unset($data[DataLoader::SCHEMA]);

		return $data;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function loadAll(string $folder): array
	{
		$directory = $this->folder($folder);
		$prefix    = trim($folder, '/') === '' ? '' : trim($folder, '/') . '/';
		$names     = [];

		if (! is_dir($directory)) {
			return [];
		}

		foreach (new DirectoryIterator($directory) as $file) {
			if ($file->isFile() && ! str_starts_with($file->getFilename(), '.') && DataLoader::isDataFile($file->getFilename())) {
				$names[] = $file->getBasename('.' . $file->getExtension());
			}
		}

		sort($names);

		$data = [];

		foreach ($names as $name) {
			$data[$name] = $this->load($prefix . $name) ?? [];
		}

		return $data;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function records(string $folder): array
	{
		$found = [];

		foreach ($this->filesystem->files($this->folder($folder)) as $relative => $file) {
			if (DataLoader::isDataFile($file->getFilename())) {
				$name = substr(str_replace('\\', '/', (string) $relative), 0, -strlen($file->getExtension()) - 1);

				$found[$name] = (int) $file->getMTime();
			}
		}

		ksort($found, SORT_STRING);

		return $found;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function save(string $name, array $data): void
	{
		$path = $this->loader->path($this->paths->data, $name);
		$text = $this->text($path);

		try {
			$json = json_encode([...self::schema($text), ...$data] ?: (object) [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
		} catch (JsonException $error) {
			throw new DataStoreException(sprintf('The values for %s can\'t be written as JSON: %s', $this->location($name), $error->getMessage()), previous: $error);
		}

		$this->remember($path, $text);

		try {
			$this->filesystem->writeAtomic($path, $json);
		} catch (Throwable $error) {
			throw new DataStoreException(sprintf('Unable to write %s.', $this->location($name)), previous: $error);
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function delete(string $name): void
	{
		$path = $this->loader->path($this->paths->data, $name);

		if (! is_file($path)) {
			return;
		}

		$this->remember($path, $this->text($path));

		if (! @unlink($path)) {
			throw new DataStoreException(sprintf('Unable to delete %s.', $this->location($name)));
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function transaction(Closure $write): mixed
	{
		$outer = $this->transactions === [];

		if ($outer) {
			$this->acquire();
		}

		$this->transactions[] = [];

		try {
			$result = $write();
		} catch (Throwable $error) {
			$this->restore((array) array_pop($this->transactions));

			if ($outer) {
				$this->release();
			}

			throw $error;
		}

		$written = (array) array_pop($this->transactions);

		if ($outer) {
			$this->release();
		} else {
			// The outer transaction keeps the text from before either wrote.
			$this->transactions[] = (array) array_pop($this->transactions) + $written;
		}

		return $result;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function location(string $name): string
	{
		return $this->paths->relative($this->loader->path($this->paths->data, $name));
	}

	/**
	 * Returns a folder's path, for an unsafe name failing as a record's
	 * would.
	 *
	 * @throws InvalidData
	 */
	private function folder(string $folder): string
	{
		$folder = trim($folder, '/');

		if ($folder === '') {
			return $this->paths->data;
		}

		return substr($this->loader->path($this->paths->data, $folder), 0, -strlen('.' . DataLoader::EXTENSION));
	}

	/**
	 * A file's text, or `null` for none.
	 */
	private function text(string $path): ?string
	{
		if (! is_file($path)) {
			return null;
		}

		$text = @file_get_contents($path);

		return $text === false ? null : $text;
	}

	/**
	 * A file's `$schema` key, to keep first.
	 *
	 * @return array<string, string>
	 */
	private static function schema(?string $text): array
	{
		$data = $text === null ? null : json_decode($text, true);

		return is_array($data) && is_string($data[DataLoader::SCHEMA] ?? null) ? [DataLoader::SCHEMA => $data[DataLoader::SCHEMA]] : [];
	}

	/**
	 * Keeps a file's text from before the open transaction first wrote it.
	 */
	private function remember(string $path, ?string $text): void
	{
		$last = array_key_last($this->transactions);

		if ($last !== null && ! array_key_exists($path, $this->transactions[$last])) {
			$this->transactions[$last][$path] = $text;
		}
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
	 * Takes the data lock.
	 *
	 * @throws DataStoreException
	 */
	private function acquire(): void
	{
		if (! is_dir($this->paths->cache)) {
			@mkdir($this->paths->cache, 0775, true);
		}

		$lock = @fopen("{$this->paths->cache}/data.lock", 'c');

		if ($lock === false || ! flock($lock, LOCK_EX)) {
			throw new DataStoreException('Unable to take the data lock.');
		}

		$this->lock = $lock;
	}

	/**
	 * Lets the data lock go.
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
