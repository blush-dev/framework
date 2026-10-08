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
use Blush\Storage\File\FileTransactions;
use Blush\Storage\StorageException;
use Blush\Support\Filesystem;

/**
 * Keeps the data area as JSON files in `user/data` (D-631, D-642): the
 * record `types/post` is `user/data/types/post.json`.
 *
 * A file's `$schema` key, which points an editor at its JSON Schema, is
 * never part of a record (D-491), and saving keeps it first. A file that
 * can't be read is named by its path from the site's root. Writes are
 * atomic, and transactions are the filesystem driver's
 * (`FileTransactions`), shared with its records.
 */
final readonly class FileDataStore implements DataStore
{
	public function __construct(
		private Paths $paths,
		private DataLoader $loader,
		private Filesystem $filesystem,
		private FileTransactions $transactions
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

		$this->transactions->remember($path);

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

		$this->transactions->remember($path);

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
		try {
			return $this->transactions->run($write);
		} catch (StorageException $error) {
			throw new DataStoreException($error->getMessage(), previous: $error);
		}
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
}
