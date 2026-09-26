<?php

/**
 * File cache store.
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
use Blush\Support\Filesystem;

/**
 * Keeps each entry in its own file under `storage/cache/store/{namespace}`,
 * named by a hash of its key. A file holds its expiry time on the first
 * line, so `prune()` reads only that, then the serialized value, which is
 * read back with no classes allowed. Writes are atomic. This is the
 * default driver: it works on any host.
 */
final class FileStore extends Store
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
		$contents = @file_get_contents($this->path($key));

		if ($contents === false) {
			return null;
		}

		[$expires, $payload] = explode("\n", $contents, 2) + [1 => ''];

		if (! ctype_digit($expires)) {
			return null;
		}

		$item = new Item(null, (int) $expires);

		if ($item->isExpired($this->now())) {
			return null;
		}

		$value = @unserialize($payload, ['allowed_classes' => false]);

		return $value === false && $payload !== serialize(false) ? null : new Item($value, $item->expires);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function write(string $key, Item $item): bool
	{
		try {
			new Filesystem()->writeAtomic($this->path($key), $item->expires . "\n" . serialize($item->value));
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
		$path = $this->path($key);

		return ! is_file($path) || @unlink($path);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function flush(): bool
	{
		$cleared = true;

		foreach ($this->files() as $file) {
			$cleared = @unlink($file->getPathname()) && $cleared;
		}

		foreach (is_dir($this->directory) ? scandir($this->directory) ?: [] : [] as $folder) {
			if ($folder !== '.' && $folder !== '..' && is_dir("{$this->directory}/{$folder}")) {
				@rmdir("{$this->directory}/{$folder}");
			}
		}

		return $cleared;
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
			$handle = @fopen($file->getPathname(), 'r');

			if ($handle === false) {
				continue;
			}

			$line = fgets($handle);
			fclose($handle);

			$expires = $line === false ? '' : trim($line);

			if (! ctype_digit($expires) || new Item(null, (int) $expires)->isExpired($now)) {
				$removed += @unlink($file->getPathname()) ? 1 : 0;
			}
		}

		return $removed;
	}

	/**
	 * Returns an entry's file: `{hash[0:2]}/{hash}`, so no folder grows
	 * too large.
	 */
	private function path(string $key): string
	{
		$hash = hash('xxh128', $key);

		return "{$this->directory}/" . substr($hash, 0, 2) . "/{$hash}.cache";
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
			if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'cache') {
				$files[] = $file;
			}
		}

		return $files;
	}
}
