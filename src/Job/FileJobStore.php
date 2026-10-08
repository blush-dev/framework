<?php

/**
 * File job store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

use JsonException;
use Override;
use Blush\Core\Paths;
use Blush\Support\Filesystem;
use Blush\Support\FilesystemException;
use Blush\Support\Uuid;

/**
 * Keeps jobs as JSON files in `storage/jobs` (D-621), a folder for each
 * status: `queued/{id}.json`, `running/…`, `done/…`, and `failed/…`.
 * A runner claims a job by renaming its file from `queued/` to
 * `running/`; a rename is atomic, so when two runners reach for one job,
 * only one rename succeeds. Named state is `{name}.json` beside the
 * folders, and a named lock is `flock()` on `{name}.lock`.
 */
final readonly class FileJobStore implements JobStore
{
	public function __construct(
		private Paths $paths,
		private Filesystem $filesystem
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function save(JobRecord $job): void
	{
		$this->write($this->file($job->status, $job->id), $job);

		foreach (JobStatus::cases() as $status) {
			if ($status !== $job->status) {
				@unlink($this->file($status, $job->id));
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function find(string $id): ?JobRecord
	{
		if (! Uuid::isValid($id)) {
			return null;
		}

		foreach (JobStatus::cases() as $status) {
			$job = $this->read($this->file($status, strtolower($id)));

			if ($job !== null) {
				return $job;
			}
		}

		return null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function all(?JobStatus $status = null): array
	{
		$files = [];

		foreach ($status === null ? JobStatus::cases() : [$status] as $case) {
			foreach (glob("{$this->paths->jobs}/{$case->value}/*.json") ?: [] as $file) {
				$files[basename($file)] = $file;
			}
		}

		// UUIDv7s sort by when they were made.
		ksort($files, SORT_STRING);

		return array_values(array_filter(array_map($this->read(...), array_values($files))));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function claim(JobRecord $queued, JobRecord $running): ?JobRecord
	{
		$from = $this->file(JobStatus::Queued, $queued->id);
		$to   = $this->file(JobStatus::Running, $queued->id);

		$this->directory(dirname($to));

		if (! @rename($from, $to)) {
			return null;
		}

		$running = $running->with(['status' => JobStatus::Running]);

		$this->write($to, $running);

		return $running;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function delete(string $id): void
	{
		if (! Uuid::isValid($id)) {
			return;
		}

		foreach (JobStatus::cases() as $status) {
			@unlink($this->file($status, strtolower($id)));
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function state(string $name): array
	{
		$contents = @file_get_contents($this->named($name, 'json'));

		if ($contents === false) {
			return [];
		}

		try {
			$state = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return [];
		}

		if (! is_array($state)) {
			return [];
		}

		/** @var array<string, mixed> */
		return array_filter($state, is_string(...), ARRAY_FILTER_USE_KEY);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function saveState(string $name, array $state): void
	{
		try {
			$this->filesystem->writeAtomic($this->named($name, 'json'), json_encode($state, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", 0660);
		} catch (FilesystemException | JsonException $e) {
			throw new JobException(sprintf('Unable to save the jobs\' "%s" state: %s', $name, $e->getMessage()), 0, $e);
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function locked(string $name, callable $task): bool
	{
		$this->directory($this->paths->jobs);

		$handle = @fopen($this->named($name, 'lock'), 'c');

		if ($handle === false) {
			return false;
		}

		try {
			if (! flock($handle, LOCK_EX | LOCK_NB)) {
				return false;
			}

			try {
				$task();
			} finally {
				flock($handle, LOCK_UN);
			}

			return true;
		} finally {
			fclose($handle);
		}
	}

	/**
	 * Writes a job's file.
	 *
	 * @throws JobException
	 */
	private function write(string $file, JobRecord $job): void
	{
		try {
			$this->filesystem->writeAtomic($file, json_encode($job->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", 0660);
		} catch (FilesystemException | JsonException $e) {
			throw new JobException(sprintf('Unable to save job %s: %s', $job->id, $e->getMessage()), 0, $e);
		}
	}

	/**
	 * Reads a job's file, or returns `null` when it's missing or damaged.
	 */
	private function read(string $file): ?JobRecord
	{
		$contents = @file_get_contents($file);

		if ($contents === false) {
			return null;
		}

		try {
			$data = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return null;
		}

		return is_array($data) ? JobRecord::fromArray($data) : null;
	}

	/**
	 * Returns a job's file under a status.
	 */
	private function file(JobStatus $status, string $id): string
	{
		return "{$this->paths->jobs}/{$status->value}/{$id}.json";
	}

	/**
	 * Returns a named file beside the status folders. Names are Blush's
	 * own, but they're kept to safe characters all the same.
	 */
	private function named(string $name, string $extension): string
	{
		return $this->paths->jobs . '/' . preg_replace('/[^a-z0-9-]/', '-', strtolower($name)) . ".{$extension}";
	}

	/**
	 * Makes a folder when it's missing.
	 */
	private function directory(string $path): void
	{
		if (! is_dir($path)) {
			@mkdir($path, 0775, true);
		}
	}
}
