<?php

/**
 * Backups.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Backup;

use JsonException;
use Throwable;
use ZipArchive;
use Psr\Clock\ClockInterface;
use Blush\Core\Framework;
use Blush\Core\Paths;
use Blush\Job\JobQueue;
use Blush\Job\JobStatus;
use Blush\Support\Filesystem;
use Blush\Support\RequestMemo;

/**
 * The site's backups (D-885): the folder `BackupConfig` names, which is
 * the list. Each backup is a `.zip` and the `{name}.json` beside it; a
 * `.zip` copied in by hand is listed from its own `backup.json`, and its
 * file beside it is written then. A backup being made has only its file
 * beside it, until its job zips the work it's done (`BackupMaker`).
 *
 * Deleting moves a backup into `.deleted/`, so Undo can put it back
 * (`restoreDeleted()`); what's there is removed for good a day later.
 * Backups aren't records in a table: they must be listed when the
 * storage they back up is what's broken, and a restore must never
 * replace the list.
 *
 * Reads are memoized for the request (D-805) and dropped on every write.
 */
final readonly class Backups
{
	/**
	 * The folder deleted backups wait in.
	 */
	public const string DELETED = '.deleted';

	/**
	 * The folder backups are made in.
	 */
	public const string WORK = '.work';

	/**
	 * The folder backups are unzipped in to restore them.
	 */
	public const string RESTORE = '.restore';

	/**
	 * How long a deleted backup can be put back, in seconds.
	 */
	public const int KEEP_DELETED = 86400;

	/**
	 * How long a backup with no job of its own can be being made before
	 * it's taken for stopped, in seconds.
	 */
	public const int STALE = 86400;

	private const string MEMO = 'backup.backups';

	public function __construct(
		private BackupConfig $config,
		private Paths $paths,
		private JobQueue $queue,
		private ClockInterface $clock,
		private RequestMemo $memo,
		private Filesystem $filesystem = new Filesystem()
	) {}

	/**
	 * Returns the folder backups are kept in.
	 */
	public function folder(): string
	{
		return $this->config->folder($this->paths->root);
	}

	/**
	 * Returns every backup, newest first. One whose job is gone or failed
	 * without saying so is listed as failed.
	 *
	 * @return list<Backup>
	 */
	public function all(): array
	{
		/** @var list<Backup> */
		return $this->memo->remember(self::MEMO, function (): array {
			$folder  = $this->folder();
			$backups = [];

			foreach (glob("{$folder}/*.json") ?: [] as $file) {
				$backup = $this->read($file);

				if ($backup !== null) {
					$backups[$backup->name] = $this->checked($backup);
				}
			}

			foreach (glob("{$folder}/*.zip") ?: [] as $file) {
				$name = basename($file, '.zip');

				if (! isset($backups[$name]) && preg_match(Backup::NAME, $name) === 1) {
					$backup = $this->fromArchive($file);

					if ($backup !== null) {
						$this->write($backup);
						$backups[$backup->name] = $backup;
					}
				}
			}

			usort($backups, static fn (Backup $a, Backup $b): int => [$b->created, $b->name] <=> [$a->created, $a->name]);

			return $backups;
		});
	}

	/**
	 * Returns a backup by name, or `null`.
	 */
	public function find(string $name): ?Backup
	{
		return array_find($this->all(), static fn (Backup $backup): bool => $backup->name === $name);
	}

	/**
	 * Returns the backup being made, or `null`.
	 */
	public function running(): ?Backup
	{
		return array_find($this->all(), static fn (Backup $backup): bool => $backup->status === BackupStatus::Running);
	}

	/**
	 * Returns a done backup's archive, or `null` when it has none.
	 */
	public function archive(Backup $backup): ?string
	{
		$file = "{$this->folder()}/{$backup->name}.zip";

		return $backup->status === BackupStatus::Done && is_file($file) ? $file : null;
	}

	/**
	 * Returns the bytes every backup's archive takes.
	 */
	public function used(): int
	{
		return array_sum(array_map(static fn (Backup $backup): int => $backup->status === BackupStatus::Done ? $backup->size : 0, $this->all()));
	}

	/**
	 * Returns the bytes free on the disk backups are kept on, or `null`
	 * when the host won't say.
	 */
	public function free(): ?int
	{
		$folder = $this->folder();

		while (! is_dir($folder) && dirname($folder) !== $folder) {
			$folder = dirname($folder);
		}

		$free = @disk_free_space($folder);

		return $free === false ? null : (int) $free;
	}

	/**
	 * Returns a new backup's name, from when it's made: the product's
	 * name and the minute, then a number when that's taken.
	 */
	public function newName(int $time): string
	{
		$base = Framework::BINARY . '-' . gmdate('Y-m-d-Hi', $time);
		$name = $base;

		for ($number = 2; $this->taken($name); $number++) {
			$name = "{$base}-{$number}";
		}

		return $name;
	}

	/**
	 * Saves the file beside a backup.
	 *
	 * @throws BackupException When it can't be written.
	 */
	public function write(Backup $backup): void
	{
		$this->ensureFolder();

		try {
			$this->filesystem->writeAtomic($this->sidecar($backup->name), json_encode($backup->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n", 0o660);
		} catch (Throwable $e) {
			throw new BackupException(sprintf('The backup %s couldn\'t be saved: %s', $backup->name, $e->getMessage()), 0, $e);
		} finally {
			$this->memo->forget();
		}
	}

	/**
	 * Changes a backup's note.
	 *
	 * @throws BackupException
	 */
	public function note(Backup $backup, string $note): Backup
	{
		$changed = $backup->with(['note' => mb_substr(trim($note), 0, Backup::NOTE_LENGTH)]);

		$this->write($changed);

		return $changed;
	}

	/**
	 * Deletes a backup: one being made is canceled (its job stops at its
	 * next chunk), and any other is moved aside, to put back with
	 * `restoreDeleted()`.
	 *
	 * @throws BackupException When it can't be moved.
	 */
	public function delete(Backup $backup): void
	{
		if ($backup->status === BackupStatus::Running) {
			$this->write($backup->with(['canceled' => true]));

			return;
		}

		$this->pruneDeleted();

		$aside = "{$this->folder()}/" . self::DELETED;

		if (! is_dir($aside) && ! @mkdir($aside, 0o770, true) && ! is_dir($aside)) {
			throw new BackupException(sprintf('The folder %s couldn\'t be made.', $aside));
		}

		foreach (['zip', 'json'] as $extension) {
			$file = "{$this->folder()}/{$backup->name}.{$extension}";

			if (is_file($file) && ! @rename($file, "{$aside}/{$backup->name}.{$extension}")) {
				$this->memo->forget();

				throw new BackupException(sprintf('The backup %s couldn\'t be deleted.', $backup->name));
			}
		}

		$this->memo->forget();
	}

	/**
	 * Adds an uploaded archive to the list (D-886): it must open as a
	 * zip, hold a `backup.json` naming a backup, and hold no path outside
	 * what a backup holds (`Backup::isSafePath()`). It's kept at the name
	 * its `backup.json` gives, as an uploaded backup; nothing about the
	 * site changes until it's restored. The file is moved from `$file`,
	 * or left where it is when it's refused.
	 *
	 * @throws BackupException When it isn't a backup.
	 * @throws BackupTaken When one has its name.
	 */
	public function upload(string $file): Backup
	{
		$this->ensureFolder();

		$zip = new ZipArchive();

		if ($zip->open($file, ZipArchive::RDONLY) !== true) {
			throw new BackupException('It isn\'t a .zip, or it\'s damaged.');
		}

		try {
			for ($index = 0; $index < $zip->numFiles; $index++) {
				$name = (string) $zip->getNameIndex($index);

				if (! str_ends_with($name, '/') && ! Backup::isSafePath($name)) {
					throw new BackupException(sprintf('It holds a file a backup doesn\'t: %s.', $name));
				}
			}

			$text = $zip->getFromName(Backup::MANIFEST);
		} finally {
			$zip->close();
		}

		try {
			$data = is_string($text) ? json_decode($text, true, 32, JSON_THROW_ON_ERROR) : null;
		} catch (JsonException) {
			$data = null;
		}

		$backup = is_array($data) ? Backup::fromArray($data, (int) filesize($file)) : null;

		if ($backup === null) {
			throw new BackupException(sprintf('It isn\'t a backup made by %s: it has no backup.json saying what it holds.', ucfirst(Framework::BINARY)));
		}

		if ($this->taken($backup->name)) {
			throw new BackupTaken(sprintf('There\'s already a backup named %s here.', $backup->name));
		}

		$target = "{$this->folder()}/{$backup->name}.zip";

		if (! @rename($file, $target) && ! (@copy($file, $target) && @unlink($file))) {
			throw new BackupException(sprintf('The backup %s couldn\'t be saved.', $backup->name));
		}

		@chmod($target, 0o660);

		$uploaded = $backup->with(['kind' => BackupKind::Uploaded]);

		$this->write($uploaded);

		return $uploaded;
	}

	/**
	 * Removes the schedule's own backups past the newest `$keep` that
	 * worked, and its failures from before the newest that worked, so a
	 * failure stays listed until a backup works again (D-887). Backups
	 * made any other way stay. They're deleted as `delete()` does, so
	 * one can be put back for a day. Returns how many it removed.
	 *
	 * @throws BackupException When one can't be moved.
	 */
	public function prune(int $keep): int
	{
		$scheduled = array_values(array_filter($this->all(), static fn (Backup $backup): bool => $backup->kind === BackupKind::Scheduled));
		$done      = array_values(array_filter($scheduled, static fn (Backup $backup): bool => $backup->status === BackupStatus::Done));
		$newest    = $done[0]->created ?? null;
		$removed   = 0;

		foreach (array_slice($done, max(0, $keep)) as $backup) {
			$this->delete($backup);
			$removed++;
		}

		foreach ($scheduled as $backup) {
			if ($backup->status === BackupStatus::Failed && $newest !== null && $backup->created < $newest) {
				$this->delete($backup);
				$removed++;
			}
		}

		return $removed;
	}

	/**
	 * Puts back a backup deleted in the last day.
	 *
	 * @throws BackupException When it isn't there to put back, or another has its name.
	 */
	public function restoreDeleted(string $name): Backup
	{
		$aside = "{$this->folder()}/" . self::DELETED;

		if (preg_match(Backup::NAME, $name) !== 1 || ! is_file("{$aside}/{$name}.json") || $this->taken($name)) {
			throw new BackupException(sprintf('The backup %s can\'t be put back.', $name));
		}

		foreach (['zip', 'json'] as $extension) {
			if (is_file("{$aside}/{$name}.{$extension}")) {
				@rename("{$aside}/{$name}.{$extension}", "{$this->folder()}/{$name}.{$extension}");
			}
		}

		$this->memo->forget();

		return $this->find($name) ?? throw new BackupException(sprintf('The backup %s can\'t be put back.', $name));
	}

	/**
	 * Removes a backup's file beside it and archive for good, and its
	 * work: what a canceled or failed backup's job leaves.
	 */
	public function discard(string $name): void
	{
		@unlink($this->sidecar($name));
		@unlink("{$this->folder()}/{$name}.zip");

		$this->memo->forget();
	}

	/**
	 * Returns the folder a backup is made in.
	 */
	public function work(string $name): string
	{
		return "{$this->folder()}/" . self::WORK . "/{$name}";
	}

	/**
	 * Makes the backups folder when it's missing.
	 *
	 * @throws BackupException When it can't be made.
	 */
	public function ensureFolder(): void
	{
		$folder = $this->folder();

		if (! is_dir($folder) && ! @mkdir($folder, 0o770, true) && ! is_dir($folder)) {
			throw new BackupException(sprintf('The backups folder, %s, couldn\'t be made.', $this->paths->relative($folder)));
		}

		if (! is_writable($folder)) {
			throw new BackupException(sprintf('The backups folder, %s, isn\'t writable.', $this->paths->relative($folder)));
		}
	}

	/**
	 * Removes what was deleted more than `KEEP_DELETED` ago.
	 */
	private function pruneDeleted(): void
	{
		$cutoff = $this->clock->now()->getTimestamp() - self::KEEP_DELETED;

		foreach (glob("{$this->folder()}/" . self::DELETED . '/*') ?: [] as $file) {
			if (is_file($file) && (int) @filemtime($file) < $cutoff) {
				@unlink($file);
			}
		}
	}

	/**
	 * Returns a backup being made whose job is gone or failed as failed,
	 * since nothing will finish it. One with no job of its own (made on
	 * the command line, or by a restore's job) is taken for stopped once
	 * it's been a day.
	 */
	private function checked(Backup $backup): Backup
	{
		if ($backup->status !== BackupStatus::Running) {
			return $backup;
		}

		if ($backup->job === null) {
			return $this->clock->now()->getTimestamp() - $backup->created < self::STALE
				? $backup
				: $backup->with(['status' => BackupStatus::Failed, 'reason' => 'It stopped before it finished. Nothing was kept.']);
		}

		$job = $this->queue->find($backup->job);

		if ($job !== null && $job->status !== JobStatus::Failed && $job->status !== JobStatus::Done) {
			return $backup;
		}

		$reason = $job?->error !== null ? (string) $job->error : 'It stopped before it finished. Nothing was kept.';

		return $backup->with(['status' => BackupStatus::Failed, 'reason' => $reason]);
	}

	/**
	 * Reads a backup from the file beside it, or `null`.
	 */
	private function read(string $file): ?Backup
	{
		try {
			$data = json_decode((string) @file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return null;
		}

		$backup = is_array($data) ? Backup::fromArray($data) : null;

		return $backup !== null && $backup->name === basename($file, '.json') ? $backup : null;
	}

	/**
	 * Reads a backup from its archive's `backup.json`, or `null` when it
	 * isn't a backup.
	 */
	private function fromArchive(string $file): ?Backup
	{
		$zip = new ZipArchive();

		if ($zip->open($file, ZipArchive::RDONLY) !== true) {
			return null;
		}

		$text = $zip->getFromName(Backup::MANIFEST);
		$zip->close();

		try {
			$data = is_string($text) ? json_decode($text, true, 32, JSON_THROW_ON_ERROR) : null;
		} catch (JsonException) {
			return null;
		}

		$backup = is_array($data) ? Backup::fromArray($data, (int) filesize($file)) : null;

		return $backup !== null && $backup->name === basename($file, '.zip') ? $backup : null;
	}

	/**
	 * Returns whether a backup, or one deleted, has the name.
	 */
	private function taken(string $name): bool
	{
		$folder = $this->folder();

		return is_file("{$folder}/{$name}.json") || is_file("{$folder}/{$name}.zip") || is_dir("{$folder}/" . self::WORK . "/{$name}");
	}

	/**
	 * Returns the file beside a backup.
	 */
	private function sidecar(string $name): string
	{
		return "{$this->folder()}/{$name}.json";
	}
}
