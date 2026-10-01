<?php

/**
 * Media index.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media\Index;

use Blush\Core\Paths;
use Blush\Media\MediaException;
use Blush\Support\FilesystemException;
use Blush\Support\PhpArrayFile;

/**
 * Stores the media index (D-288): `storage/index/media.php`, a PHP file
 * returning the snapshot's array, beside the content index but apart from
 * it, so reindexing content doesn't walk the media, and the other way
 * round. Opcache keeps it in shared memory.
 */
final class MediaIndex
{
	/**
	 * The index file's name in `storage/index`.
	 */
	public const string FILE = 'media.php';

	private ?MediaSnapshot $snapshot = null;

	private readonly PhpArrayFile $file;

	public function __construct(Paths $paths)
	{
		$this->file = new PhpArrayFile("{$paths->index}/" . self::FILE);
	}

	public function path(): string
	{
		return $this->file->path;
	}

	public function exists(): bool
	{
		return $this->file->exists();
	}

	public function snapshot(): MediaSnapshot
	{
		if ($this->snapshot !== null) {
			return $this->snapshot;
		}

		try {
			$data = $this->file->read();
		} catch (FilesystemException) {
			$data = null;
		}

		return $this->snapshot = $data === null ? new MediaSnapshot() : MediaSnapshot::fromArray($data);
	}

	/**
	 * @throws MediaException When it can't be written.
	 */
	public function save(MediaSnapshot $snapshot): void
	{
		try {
			$this->file->write($snapshot->toArray());
		} catch (FilesystemException $e) {
			throw new MediaException(sprintf('Unable to write the media index: %s', $e->getMessage()), previous: $e);
		}

		$this->snapshot = $snapshot;
	}

	public function clear(): void
	{
		$this->file->delete();
		$this->snapshot = null;
	}
}
