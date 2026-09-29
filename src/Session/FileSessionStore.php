<?php

/**
 * File session store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Session;

use JsonException;
use Override;
use Blush\Core\Paths;
use Blush\Support\Filesystem;
use Blush\Support\FilesystemException;

/**
 * Keeps each session in a JSON file in `storage/sessions`. The file is
 * named for a SHA-256 hash of the id, so the folder's listing never
 * reveals a live session id, and it's readable only by its owner and
 * group.
 */
final readonly class FileSessionStore implements SessionStore
{
	public function __construct(
		private Paths $paths,
		private Filesystem $filesystem
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function read(string $id): ?array
	{
		$contents = @file_get_contents($this->file($id));

		if ($contents === false) {
			return null;
		}

		try {
			$record = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return null;
		}

		if (! is_array($record) || ! is_int($record['created'] ?? null) || ! is_int($record['lastSeen'] ?? null) || ! is_array($record['data'] ?? null)) {
			return null;
		}

		/** @var array<string, mixed> $data */
		$data = $record['data'];

		return ['created' => $record['created'], 'lastSeen' => $record['lastSeen'], 'data' => $data];
	}

	/**
	 * @inheritDoc
	 * @throws FilesystemException When the file can't be written.
	 * @throws JsonException
	 */
	#[Override]
	public function write(string $id, array $record): void
	{
		$this->filesystem->writeAtomic($this->file($id), json_encode($record, JSON_THROW_ON_ERROR), 0660);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function delete(string $id): void
	{
		@unlink($this->file($id));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function prune(int $before): int
	{
		$pruned = 0;

		foreach (glob("{$this->paths->sessions}/*.json") ?: [] as $file) {
			$modified = @filemtime($file);

			if ($modified !== false && $modified < $before && @unlink($file)) {
				$pruned++;
			}
		}

		return $pruned;
	}

	/**
	 * Returns a session's file.
	 */
	private function file(string $id): string
	{
		return $this->paths->sessions . '/' . hash('sha256', $id) . '.json';
	}
}
