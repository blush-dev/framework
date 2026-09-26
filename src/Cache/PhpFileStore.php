<?php

/**
 * PHP file cache store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Cache;

use FilesystemIterator;
use Override;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;
use Psr\Clock\ClockInterface;
use Blush\Core\Paths;
use Blush\Support\PhpArrayFile;

/**
 * Keeps each entry in a PHP file that returns it (`PhpArrayFile`), under
 * `storage/cache/store/{namespace}`, so opcache holds hot entries in
 * shared memory and reading one costs no parsing. Suited to small values
 * read on every request (compiled design tokens); large ones (whole
 * pages) are better in the `file` driver, which doesn't fill opcache.
 */
final class PhpFileStore extends Store
{
	private readonly string $directory;

	public function __construct(string $namespace, ClockInterface $clock, Paths $paths)
	{
		parent::__construct($namespace, $clock);

		$this->directory = "{$paths->cache}/store/{$namespace}";
	}

	/**
	 * Returns the namespace's folder.
	 */
	public function directory(): string
	{
		return $this->directory;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function read(string $key): ?Item
	{
		return $this->load($this->path($key));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function write(string $key, Item $item): bool
	{
		try {
			new PhpArrayFile($this->path($key))->write($item->toArray());
		} catch (Throwable) {
			return false;
		}

		return true;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function remove(string $key): bool
	{
		new PhpArrayFile($this->path($key))->delete();

		return true;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function flush(): bool
	{
		foreach ($this->files() as $file) {
			new PhpArrayFile($file->getPathname())->delete();
		}

		foreach (is_dir($this->directory) ? scandir($this->directory) ?: [] : [] as $folder) {
			if ($folder !== '.' && $folder !== '..' && is_dir("{$this->directory}/{$folder}")) {
				@rmdir("{$this->directory}/{$folder}");
			}
		}

		return true;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function prune(): int
	{
		$now     = $this->now();
		$removed = 0;

		foreach ($this->files() as $file) {
			$item = $this->load($file->getPathname());

			if ($item === null || $item->isExpired($now)) {
				new PhpArrayFile($file->getPathname())->delete();
				$removed++;
			}
		}

		return $removed;
	}

	/**
	 * Reads an entry file, or returns `null` when it's missing or damaged.
	 */
	private function load(string $path): ?Item
	{
		try {
			return Item::fromArray(new PhpArrayFile($path)->read());
		} catch (Throwable) {
			return null;
		}
	}

	/**
	 * Returns an entry's file.
	 */
	private function path(string $key): string
	{
		$hash = hash('xxh128', $key);

		return "{$this->directory}/" . substr($hash, 0, 2) . "/{$hash}.php";
	}

	/**
	 * Returns every entry file in the namespace.
	 *
	 * @return list<SplFileInfo>
	 */
	private function files(): array
	{
		if (! is_dir($this->directory)) {
			return [];
		}

		$files = [];

		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS)) as $file) {
			if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
				$files[] = $file;
			}
		}

		return $files;
	}
}
