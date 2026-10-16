<?php

/**
 * Backup restorer.
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
use Blush\Auth\Account;
use Blush\Auth\Accounts;
use Blush\Cache\Caches;
use Blush\Cache\ContentVersion;
use Blush\Content\Index\Indexer;
use Blush\Core\Bootstrap;
use Blush\Core\CompiledCache;
use Blush\Core\Framework;
use Blush\Job\JobQueue;
use Blush\Job\JobRecord;
use Blush\Job\JobType;
use Blush\Job\Jobs\MediaIndexJob;
use Blush\Media\Index\MediaIndexer;
use Blush\Session\SessionStore;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageConfig;
use Blush\Storage\StorageException;
use Blush\Storage\StorageResolver;
use Blush\Support\Filesystem;
use Blush\Support\RequestMemo;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Restores parts of a backup (D-886), a step at a time, so it runs as a
 * job (`RestoreJob`), or all at once on the command line (`restore()`):
 *
 * 1. **A backup first:** the parts as they are now, kept and noted
 *    "Before restoring {name}", so a restore can be undone by restoring
 *    that one.
 * 2. **Unzip** the backup into a work folder, checking every path in it.
 * 3. **Each table** is replaced by its driver (`TableBackup::restore()`),
 *    a chunk at a time.
 * 4. **Then** the compiled types and routes are cleared, content and
 *    media reindexed, caches cleared, and, when Accounts was restored,
 *    every session ended.
 *
 * `check()` says first what refuses a restore (a backup that isn't done,
 * from a newer Blush or format, or of an area another driver kept) and
 * what it will do that's worth knowing. One backup or restore runs at a
 * time.
 */
final readonly class BackupRestorer
{
	/**
	 * The parts restoring signs everyone out for.
	 */
	public const string SIGNS_OUT = 'accounts';

	public function __construct(
		private Backups $backups,
		private BackupMaker $maker,
		private BackupParts $parts,
		private StorageConfig $storage,
		private StorageResolver $resolver,
		private JobQueue $queue,
		private Accounts $accounts,
		private Bootstrap $bootstrap,
		private Indexer $indexer,
		private MediaIndexer $media,
		private Caches $caches,
		private ContentVersion $version,
		private SessionStore $sessions,
		private RequestMemo $memo,
		private Messages $messages,
		private Filesystem $filesystem = new Filesystem()
	) {}

	/**
	 * Returns what refuses restoring the parts of a backup, and what it
	 * will do.
	 *
	 * @param list<string> $parts
	 */
	public function check(Backup $backup, array $parts): RestoreCheck
	{
		$problems = [];
		$warnings = [];
		$parts    = array_values(array_unique($parts));

		if ($backup->status !== BackupStatus::Done || $this->backups->archive($backup) === null) {
			$problems[] = $this->messages->t('backup.restorer.not_done', 'Only a finished backup can be restored.');
		}

		if ($backup->formatVersion < 1) {
			$problems[] = $this->messages->t('backup.restorer.unversioned', 'This backup was made before backups said their format, so it can\'t be restored.');
		} elseif ($backup->formatVersion > Backup::FORMAT_VERSION) {
			$problems[] = $this->messages->t('backup.restorer.newer_format', 'This backup was made by a newer Blush ({version}). Update Blush to restore it.', version: $backup->blushVersion);
		} elseif ($backup->blushVersion !== '' && version_compare($backup->blushVersion, Framework::VERSION, '>')) {
			$problems[] = $this->messages->t('backup.restorer.newer_blush', 'This backup was made by a newer Blush ({version}). Update Blush to restore it.', version: $backup->blushVersion);
		}

		if ($parts === []) {
			$problems[] = $this->messages->t('backup.restorer.no_parts', 'Pick at least one thing to restore.');
		}

		foreach ($parts as $name) {
			$part = $this->parts->get($name);

			if ($part === null || ! isset($backup->parts[$name])) {
				$problems[] = $this->messages->t('backup.restorer.not_held', 'This backup doesn\'t hold "{part}".', part: $name);

				continue;
			}

			foreach ($this->parts->tables($part) as $table) {
				$then = $backup->drivers[$table->area->value] ?? null;
				$now  = $this->storage->driverFor($table->area);

				if ($then !== null && $then !== $now) {
					$problems[] = $this->messages->t('backup.restorer.other_driver', '{part} was backed up from {then}, and the site keeps it in {now} now. Restore it to a site on {then}, then move it with storage:copy.', part: (string) $part->label, then: $then, now: $now);

					break;
				}
			}
		}

		if ($this->backups->running() !== null || $this->running() !== null) {
			$problems[] = $this->messages->t('backup.restorer.busy', 'A backup is being made or restored. Wait for it to finish first.');
		}

		if (in_array(self::SIGNS_OUT, $parts, true)) {
			$warnings[] = $this->messages->t('backup.restorer.signs_out', 'Restoring accounts signs everyone out, you included. Passwords go back to what they were when the backup was made, and an account made since is gone.');
		}

		if (in_array(CorePart::Content->value, $parts, true) && (! in_array(CorePart::Types->value, $parts, true) || ! in_array(CorePart::Relationships->value, $parts, true))) {
			$warnings[] = $this->messages->t('backup.restorer.links', 'Content is restored with the types and relationships the site has now. An entry of a type that\'s gone won\'t show, and links through a relationship that\'s gone are dropped.');
		}

		return new RestoreCheck($problems, $warnings);
	}

	/**
	 * Returns the restore running, or `null`.
	 */
	public function running(): ?JobRecord
	{
		return $this->queue->waiting(JobType::Restore->value);
	}

	/**
	 * Starts restoring parts of a backup, as a job.
	 *
	 * @param  list<string> $parts
	 * @throws BackupException When `check()` refuses it, with its first problem.
	 */
	public function start(Backup $backup, array $parts, ?Account $account = null): JobRecord
	{
		$parts = $this->ordered($parts);

		$this->refuse($backup, $parts);

		return $this->queue->push(JobType::Restore->value, ['backup' => $backup->name, 'parts' => $parts, 'account' => $account?->id], $account?->id, JobType::Restore->value);
	}

	/**
	 * Restores parts of a backup, start to end, telling `$step` what each
	 * chunk did: the command line's way. Returns the backup made first.
	 *
	 * @param  list<string>                 $parts
	 * @param  Closure(Text, int): void|null $step
	 * @throws BackupException
	 */
	public function restore(Backup $backup, array $parts, ?Account $account = null, ?Closure $step = null): Backup
	{
		$parts = $this->ordered($parts);

		$this->refuse($backup, $parts);

		$data = ['backup' => $backup->name, 'parts' => $parts, 'account' => $account?->id];

		do {
			$result = $this->step($data);
			$data   = $result['data'] ?? $data;

			if ($step !== null) {
				$step($result['message'], $result['progress']);
			}
		} while ($result['data'] !== null);

		$safety = $result['result']['safety'] ?? null;

		return (is_string($safety) ? $this->backups->find($safety) : null) ?? throw new BackupException('The restore finished without its backup of the site first.');
	}

	/**
	 * Does a restore's next step, from what the last left, and returns
	 * what the next needs (`null` once it's done), how far along it is,
	 * what it did, and, at the end, the backup made first (`safety`) and
	 * whether everyone was signed out.
	 *
	 * @param  array<string, mixed> $data
	 * @return array{data: array<string, mixed>|null, progress: int, message: Text, result: array<string, mixed>}
	 * @throws BackupException When a step fails; what's done so far stays done.
	 */
	public function step(array $data): array
	{
		$this->memo->forget();

		$name   = is_string($data['backup'] ?? null) ? $data['backup'] : '';
		$backup = $this->backups->find($name) ?? throw new BackupException((string) $this->messages->t('backup.restorer.missing', 'There\'s no backup named {name}.', name: $name));
		$parts  = is_array($data['parts'] ?? null) ? array_values(array_filter($data['parts'], is_string(...))) : [];
		$phase  = is_string($data['phase'] ?? null) ? $data['phase'] : 'safety';
		$state  = is_array($data['state'] ?? null) ? $data['state'] : [];

		try {
			return match ($phase) {
				'safety'  => $this->safety($backup, $parts, $data, $state),
				'unzip'   => $this->unzip($backup, $data),
				'tables'  => $this->tables($backup, $parts, $data, $state),
				default   => $this->finish($backup, $parts, $data)
			};
		} catch (BackupException $e) {
			$this->clean($backup);

			throw $e;
		} catch (Throwable $e) {
			$this->clean($backup);

			throw new BackupException($e->getMessage(), 0, $e);
		}
	}

	/**
	 * Backs up the parts as they are now, a chunk at a time.
	 *
	 * @param  list<string>         $parts
	 * @param  array<string, mixed> $data
	 * @param  array<mixed>         $state
	 * @return array{data: array<string, mixed>, progress: int, message: Text, result: array<string, mixed>}
	 */
	private function safety(Backup $backup, array $parts, array $data, array $state): array
	{
		$safety = is_string($data['safety'] ?? null) ? $data['safety'] : null;

		if ($safety === null) {
			$account = is_string($data['account'] ?? null) ? $this->accounts->findById($data['account']) : null;
			$safety  = $this->maker->prepare($parts, (string) $this->messages->t('backup.restorer.safety_note', 'Before restoring {name}', name: $backup->name), $account, BackupKind::BeforeRestore)->name;
			$state   = [];
		}

		/** @var array<string, mixed> $state */
		$made = $this->maker->step($safety, $state);
		$next = $made['state'] === null
			? [...$data, 'safety' => $safety, 'phase' => 'unzip', 'state' => []]
			: [...$data, 'safety' => $safety, 'state' => $made['state']];

		return [
			'data'     => $next,
			'progress' => intdiv($made['progress'] * 40, 100),
			'message'  => $this->messages->t('backup.restorer.backing_up', 'Backing up the site as it is now'),
			'result'   => []
		];
	}

	/**
	 * Unzips the backup into the restore's work folder, refusing a path
	 * that would land outside it.
	 *
	 * @param  array<string, mixed> $data
	 * @return array{data: array<string, mixed>, progress: int, message: Text, result: array<string, mixed>}
	 * @throws BackupException
	 */
	private function unzip(Backup $backup, array $data): array
	{
		$archive = $this->backups->archive($backup) ?? throw new BackupException(sprintf('The backup %s has no archive.', $backup->name));
		$work    = $this->work($backup);

		$this->clean($backup);

		if (! @mkdir($work, 0o770, true) && ! is_dir($work)) {
			throw new BackupException(sprintf('The folder %s couldn\'t be made.', $work));
		}

		$zip = new ZipArchive();

		if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
			throw new BackupException(sprintf('The backup %s couldn\'t be opened.', $backup->name));
		}

		try {
			for ($index = 0; $index < $zip->numFiles; $index++) {
				$name = (string) $zip->getNameIndex($index);

				if (str_ends_with($name, '/')) {
					continue;
				}

				if (! Backup::isSafePath($name)) {
					throw new BackupException(sprintf('The backup %s holds a file it shouldn\'t: %s.', $backup->name, $name));
				}

				$target = "{$work}/{$name}";
				$stream = $zip->getStreamIndex($index);

				if ($stream === false || (! is_dir(dirname($target)) && ! @mkdir(dirname($target), 0o770, true) && ! is_dir(dirname($target)))) {
					throw new BackupException(sprintf('%s couldn\'t be unzipped.', $name));
				}

				$out = @fopen($target, 'wb');

				if ($out === false) {
					fclose($stream);

					throw new BackupException(sprintf('%s couldn\'t be unzipped.', $name));
				}

				stream_copy_to_stream($stream, $out);
				fclose($out);
				fclose($stream);
			}
		} finally {
			$zip->close();
		}

		return [
			'data'     => [...$data, 'phase' => 'tables', 'state' => []],
			'progress' => 45,
			'message'  => $this->messages->t('backup.restorer.unzipped', 'Opened the backup'),
			'result'   => []
		];
	}

	/**
	 * Restores the next chunk of a table.
	 *
	 * @param  list<string>         $parts
	 * @param  array<string, mixed> $data
	 * @param  array<mixed>         $state
	 * @return array{data: array<string, mixed>, progress: int, message: Text, result: array<string, mixed>}
	 * @throws BackupException
	 */
	private function tables(Backup $backup, array $parts, array $data, array $state): array
	{
		$plan   = $this->plan($parts);
		$index  = is_int($state['table'] ?? null) ? $state['table'] : 0;
		$cursor = is_int($state['cursor'] ?? null) ? $state['cursor'] : 0;

		if ($index >= count($plan)) {
			return ['data' => [...$data, 'phase' => 'finish', 'state' => []], 'progress' => 95, 'message' => $this->messages->t('backup.restorer.restored_tables', 'Restored the backup\'s records'), 'result' => []];
		}

		[$part, $table] = $plan[$index];

		$driver = $this->driver($table) ?? throw new BackupException(sprintf('The "%s" storage driver can\'t restore its tables.', $this->storage->driverFor($table->area)));
		$next   = $driver->restore($table, $this->work($backup), $cursor);
		$label  = $this->parts->get($part)->label ?? $part;

		return [
			'data'     => [...$data, 'state' => $next === null ? ['table' => $index + 1, 'cursor' => 0] : ['table' => $index, 'cursor' => $next]],
			'progress' => 45 + intdiv(($index + ($next === null ? 1 : 0)) * 50, max(1, count($plan))),
			'message'  => $this->messages->t('backup.restorer.restoring', 'Restoring {part}', part: (string) $label),
			'result'   => []
		];
	}

	/**
	 * Brings the site up to date with what was restored.
	 *
	 * @param  list<string>         $parts
	 * @param  array<string, mixed> $data
	 * @return array{data: null, progress: int, message: Text, result: array<string, mixed>}
	 */
	private function finish(Backup $backup, array $parts, array $data): array
	{
		$this->clean($backup);
		$this->bootstrap->clearCompiled(CompiledCache::ContentTypes, CompiledCache::Routes);
		$this->memo->forget();

		$this->indexer->index(full: true);

		$media = $this->media->index(full: true, limit: MediaIndexJob::BATCH);

		if ($media->pending > 0) {
			$this->queue->push(JobType::MediaIndex->value, unique: JobType::MediaIndex->value);
		}

		$this->caches->clear();
		$this->version->bump();

		$signedOut = in_array(self::SIGNS_OUT, $parts, true);

		if ($signedOut) {
			$this->sessions->prune(PHP_INT_MAX);
		}

		$labels = array_map(fn (string $name): string => (string) ($this->parts->get($name)->label ?? $name), $parts);

		return [
			'data'     => null,
			'progress' => 100,
			'message'  => $this->messages->t('backup.restorer.done', 'Restored {parts} from {name}.', parts: implode(', ', $labels), name: $backup->name),
			'result'   => ['backup' => $backup->name, 'parts' => $parts, 'safety' => $data['safety'] ?? null, 'signedOut' => $signedOut]
		];
	}

	/**
	 * Throws the first problem `check()` finds.
	 *
	 * @param  list<string> $parts
	 * @throws BackupException
	 */
	private function refuse(Backup $backup, array $parts): void
	{
		$check = $this->check($backup, $parts);

		if (! $check->allowed()) {
			$problem = (string) $check->problems[0];

			throw $this->running() !== null || $this->backups->running() !== null ? new BackupBusy($problem) : new BackupException($problem);
		}
	}

	/**
	 * Returns the parts in the order they're listed.
	 *
	 * @param  list<string> $parts
	 * @return list<string>
	 */
	private function ordered(array $parts): array
	{
		return array_values(array_filter($this->parts->names(), static fn (string $name): bool => in_array($name, $parts, true)))
			?: array_values(array_unique($parts));
	}

	/**
	 * Returns every table to restore, in order, with its part.
	 *
	 * @param  list<string> $parts
	 * @return list<array{string, Table}>
	 */
	private function plan(array $parts): array
	{
		$plan = [];

		foreach ($parts as $name) {
			$part = $this->parts->get($name);

			foreach ($part === null ? [] : $this->parts->tables($part) as $table) {
				$plan[] = [$name, $table];
			}
		}

		return $plan;
	}

	/**
	 * Returns the folder a backup is unzipped into to restore it.
	 */
	private function work(Backup $backup): string
	{
		return "{$this->backups->folder()}/" . Backups::RESTORE . "/{$backup->name}";
	}

	/**
	 * Removes a restore's work folder.
	 */
	private function clean(Backup $backup): void
	{
		try {
			$this->filesystem->removeDirectory($this->work($backup));
		} catch (Throwable) {
			// Unzipped again over it by the next restore.
		}
	}

	/**
	 * Returns the driver's backup for a table's area, or `null`.
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
