<?php

/**
 * Backup maker.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Backup;

use Closure;
use Throwable;
use ZipArchive;
use Psr\Clock\ClockInterface;
use Blush\Auth\Account;
use Blush\Backup\Events\BackupMade;
use Blush\Core\Framework;
use Blush\Event\Dispatcher;
use Blush\Job\JobQueue;
use Blush\Job\JobType;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageConfig;
use Blush\Storage\StorageException;
use Blush\Storage\StorageResolver;
use Blush\Support\Filesystem;
use Blush\Support\RequestMemo;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Makes backups (D-886), a chunk at a time, so a large site's backup runs
 * as a job across several requests (`BackupJob`), or all at once on the
 * command line (`make()`). Starting one writes the file beside it,
 * running, with each part's count and bytes; each chunk copies the next
 * of a table's files into the backup's work folder through the driver
 * that keeps it (`TableBackup`, D-882); the last tidies each driver's
 * copy, writes `backup.json`, zips the work folder, and marks it done.
 * One backup is made at a time. Once one is kept, `BackupMade` is
 * dispatched (D-899), for plugins that copy backups elsewhere.
 *
 * A failed chunk marks the backup failed with its reason and leaves no
 * archive; a canceled one is removed at its next chunk.
 */
final readonly class BackupMaker
{
	public function __construct(
		private Backups $backups,
		private BackupParts $parts,
		private StorageConfig $storage,
		private StorageResolver $resolver,
		private RecordStores $stores,
		private JobQueue $queue,
		private ClockInterface $clock,
		private RequestMemo $memo,
		private Messages $messages,
		private Dispatcher $events,
		private Filesystem $filesystem = new Filesystem()
	) {}

	/**
	 * Returns each part's records and bytes as kept now, by name: what a
	 * backup of it will hold.
	 *
	 * @return array<string, array{count: int, bytes: int}>
	 */
	public function measure(): array
	{
		/** @var array<string, array{count: int, bytes: int}> */
		return $this->memo->remember('backup.measure', function (): array {
			$measured = [];

			foreach ($this->parts->all() as $name => $part) {
				$count = 0;
				$bytes = 0;

				foreach ($this->parts->tables($part) as $table) {
					$count += $this->count($table);
					$bytes += $this->driver($table)?->bytes($table) ?? 0;
				}

				$measured[$name] = ['count' => $count, 'bytes' => $bytes];
			}

			return $measured;
		});
	}

	/**
	 * Starts a backup of the parts given and queues its job.
	 *
	 * @param  list<string> $parts
	 * @throws BackupException When a part is unknown, none is given, one is being made, or the folder can't be written.
	 */
	public function start(array $parts, string $note = '', ?Account $account = null, BackupKind $kind = BackupKind::Manual): Backup
	{
		$backup = $this->begin($parts, $note, $account, $kind);
		$job    = $this->queue->push(JobType::Backup->value, ['name' => $backup->name], $account?->id);
		$backup = $backup->with(['job' => $job->id]);

		$this->backups->write($backup);

		return $backup;
	}

	/**
	 * Makes a backup of the parts given, start to end, telling `$step`
	 * what each chunk did: the command line's way.
	 *
	 * @param  list<string>                 $parts
	 * @param  Closure(Text, int): void|null $step
	 * @throws BackupException
	 */
	public function make(array $parts, string $note = '', ?Account $account = null, BackupKind $kind = BackupKind::Manual, ?Closure $step = null): Backup
	{
		$backup = $this->prepare($parts, $note, $account, $kind);
		$state  = [];

		do {
			$result = $this->step($backup->name, $state);
			$state  = $result['state'];

			if ($step !== null) {
				$step($result['message'], $result['progress']);
			}
		} while ($state !== null);

		return $this->backups->find($backup->name) ?? throw new BackupException(sprintf('The backup %s was canceled.', $backup->name));
	}

	/**
	 * Does a backup's next chunk, from the state the last one left (empty
	 * to start), and returns the state for the next, `null` once it's
	 * done, with how far along it is and what it did.
	 *
	 * @param  array<string, mixed> $state
	 * @return array{state: array<string, mixed>|null, progress: int, message: Text}
	 * @throws BackupException When the chunk fails; the backup is marked failed.
	 */
	public function step(string $name, array $state): array
	{
		$this->memo->forget();

		$backup = $this->backups->find($name) ?? throw new BackupException(sprintf('There\'s no backup named %s.', $name));

		if ($backup->canceled) {
			$this->clean($backup);
			$this->backups->discard($backup->name);

			return ['state' => null, 'progress' => 100, 'message' => $this->messages->t('backup.maker.canceled', 'Canceled the backup. Nothing was kept.')];
		}

		try {
			return $this->advance($backup, $state);
		} catch (Throwable $e) {
			$this->fail($backup, $e->getMessage());

			throw $e instanceof BackupException ? $e : new BackupException($e->getMessage(), 0, $e);
		}
	}

	/**
	 * Marks a backup failed, with why, and removes its work.
	 */
	public function fail(Backup $backup, string $reason): void
	{
		$this->clean($backup);
		@unlink("{$this->backups->folder()}/{$backup->name}.zip");

		try {
			$this->backups->write($backup->with(['status' => BackupStatus::Failed, 'reason' => $reason]));
		} catch (BackupException) {
			// A folder that can't be written can't say so either.
		}
	}

	/**
	 * Saves a backup of the parts given as being made, to make with
	 * `step()`: what `make()` and a restore's backup of the site first
	 * (`BackupRestorer`) start with.
	 *
	 * @param  list<string> $parts
	 * @throws BackupException
	 */
	public function prepare(array $parts, string $note = '', ?Account $account = null, BackupKind $kind = BackupKind::Manual): Backup
	{
		$backup = $this->begin($parts, $note, $account, $kind);

		$this->backups->write($backup);

		return $backup;
	}

	/**
	 * Checks the parts and the folder, and returns the backup to start.
	 * Only a restore's own backup is made while a restore runs.
	 *
	 * @param  list<string> $parts
	 * @throws BackupException
	 */
	private function begin(array $parts, string $note, ?Account $account, BackupKind $kind): Backup
	{
		$parts = array_values(array_unique($parts));

		if ($parts === []) {
			throw new BackupException((string) $this->messages->t('backup.maker.no_parts', 'Pick at least one thing to back up.'));
		}

		foreach ($parts as $part) {
			if ($this->parts->get($part) === null) {
				throw new BackupException((string) $this->messages->t('backup.maker.unknown_part', 'There\'s no part named "{part}" to back up.', part: $part));
			}
		}

		if ($this->backups->running() !== null || ($kind !== BackupKind::BeforeRestore && $this->queue->waiting(JobType::Restore->value) !== null)) {
			throw new BackupBusy((string) $this->messages->t('backup.maker.busy', 'A backup is being made or restored. Wait for it to finish first.'));
		}

		$this->backups->ensureFolder();

		$measured = $this->measure();
		$now      = $this->clock->now()->getTimestamp();
		$held     = [];
		$drivers  = [];

		// In the order parts are listed, whatever order they were asked in.
		foreach ($this->parts->all() as $name => $part) {
			if (! in_array($name, $parts, true)) {
				continue;
			}

			$tables = $this->parts->tables($part);

			$held[$name] = [...($measured[$name] ?? ['count' => 0, 'bytes' => 0]), 'tables' => array_map(static fn (Table $table): string => $table->name, $tables)];

			foreach ($tables as $table) {
				$drivers[$table->area->value] = $this->storage->driverFor($table->area);
			}
		}

		return new Backup(
			name: $this->backups->newName($now),
			status: BackupStatus::Running,
			kind: $kind,
			created: $now,
			account: $account?->id,
			note: mb_substr(trim($note), 0, Backup::NOTE_LENGTH),
			parts: $held,
			drivers: $drivers,
			blushVersion: Framework::VERSION
		);
	}

	/**
	 * Copies the next chunk, or finishes.
	 *
	 * @param  array<string, mixed> $state
	 * @return array{state: array<string, mixed>|null, progress: int, message: Text}
	 * @throws BackupException
	 */
	private function advance(Backup $backup, array $state): array
	{
		$plan   = $this->plan($backup);
		$index  = is_int($state['table'] ?? null) ? $state['table'] : 0;
		$cursor = is_int($state['cursor'] ?? null) ? $state['cursor'] : 0;
		$work   = $this->backups->work($backup->name);

		if (! is_dir($work) && ! @mkdir($work, 0o770, true) && ! is_dir($work)) {
			throw new BackupException(sprintf('The folder %s couldn\'t be made.', $work));
		}

		if ($index < count($plan)) {
			[$part, $table] = $plan[$index];

			$driver = $this->driver($table) ?? throw new BackupException(sprintf('The "%s" storage driver, named for %s, can\'t back up its tables.', $this->storage->driverFor($table->area), $table->area->value));
			$next   = $driver->copy($table, $work, $cursor);
			$label  = $this->parts->get($part)->label ?? $part;

			$state = $next === null ? ['table' => $index + 1, 'cursor' => 0] : ['table' => $index, 'cursor' => $next];

			return [
				'state'    => $state,
				'progress' => intdiv(($index + ($next === null ? 1 : 0)) * 95, max(1, count($plan))),
				'message'  => $this->messages->t('backup.maker.copying', 'Copying {part}', part: (string) $label)
			];
		}

		$this->finish($backup, $plan, $work);

		return ['state' => null, 'progress' => 100, 'message' => $this->messages->t('backup.maker.done', 'Backed up the site.')];
	}

	/**
	 * Tidies each driver's copy, writes `backup.json`, zips the work, and
	 * marks the backup done.
	 *
	 * @param  list<array{string, Table}> $plan
	 * @throws BackupException
	 */
	private function finish(Backup $backup, array $plan, string $work): void
	{
		$byDriver = [];

		foreach ($plan as [, $table]) {
			$driver = $this->driver($table);

			if ($driver !== null) {
				$byDriver[$driver->driver()] ??= [$driver, []];
				$byDriver[$driver->driver()][1][] = $table;
			}
		}

		foreach ($byDriver as [$driver, $tables]) {
			$driver->finish($tables, $work);
		}

		$done = $backup->with(['status' => BackupStatus::Done, 'job' => null]);

		$this->filesystem->writeAtomic("{$work}/" . Backup::MANIFEST, json_encode($done->manifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n", 0o660);

		$archive = "{$this->backups->folder()}/{$backup->name}.zip";
		$partial = "{$archive}.partial";

		$this->zip($work, $partial);

		if (! @rename($partial, $archive)) {
			@unlink($partial);

			throw new BackupException(sprintf('The backup %s couldn\'t be saved.', $backup->name));
		}

		@chmod($archive, 0o660);

		$this->clean($backup);

		$made = $done->with(['size' => (int) filesize($archive)]);

		$this->backups->write($made);
		$this->events->dispatch(new BackupMade($made, $archive));
	}

	/**
	 * Zips a folder's files, by path from it.
	 *
	 * @throws BackupException
	 */
	private function zip(string $folder, string $file): void
	{
		@unlink($file);

		$zip = new ZipArchive();

		if ($zip->open($file, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
			throw new BackupException(sprintf('The archive %s couldn\'t be made.', basename($file)));
		}

		foreach ($this->filesystem->files($folder, links: false) as $relative => $info) {
			$zip->addFile($info->getPathname(), $relative);
		}

		// Dotfiles are left out by the listing; `backup.json` isn't one.
		if (! $zip->close()) {
			@unlink($file);

			throw new BackupException(sprintf('The archive %s couldn\'t be written: %s', basename($file), $zip->getStatusString()));
		}
	}

	/**
	 * Returns every table the backup copies, in order, with its part.
	 *
	 * @return list<array{string, Table}>
	 */
	private function plan(Backup $backup): array
	{
		$plan = [];

		foreach ($backup->parts as $name => $held) {
			$part = $this->parts->get($name);

			if ($part === null) {
				throw new BackupException(sprintf('There\'s no part named "%s" to back up.', $name));
			}

			foreach ($this->parts->tables($part) as $table) {
				if (in_array($table->name, $held['tables'], true)) {
					$plan[] = [$name, $table];
				}
			}
		}

		return $plan;
	}

	/**
	 * Removes a backup's work folder.
	 */
	private function clean(Backup $backup): void
	{
		try {
			$this->filesystem->removeDirectory($this->backups->work($backup->name));
		} catch (Throwable) {
			// Left for the next backup of the name, which can't be.
		}
	}

	/**
	 * Returns how many records a table holds, or 0 when it can't be read.
	 */
	private function count(Table $table): int
	{
		try {
			return $this->stores->query($table)->count();
		} catch (Throwable) {
			return 0;
		}
	}

	/**
	 * Returns the driver's backup for a table's area, or `null` when the
	 * driver has none.
	 */
	private function driver(Table $table): ?TableBackup
	{
		try {
			return $this->resolver->covers($table->area, TableBackup::class)
				? $this->resolver->resolve($table->area, TableBackup::class)
				: null;
		} catch (StorageException) {
			return null;
		}
	}
}
