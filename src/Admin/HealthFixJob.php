<?php

/**
 * Health fix job.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Override;
use Blush\Auth\Account;
use Blush\Auth\Accounts;
use Blush\Content\CreatedEntries;
use Blush\Content\EntryIds;
use Blush\Content\EntryRefs;
use Blush\Content\FileNameRename;
use Blush\Content\FileNames;
use Blush\Content\EntryFolders;
use Blush\Content\MissingParents;
use Blush\Content\MissingTerms;
use Blush\Content\Writer\AssignedIds;
use Blush\Content\Writer\FiledRefs;
use Blush\Content\Writer\RenamedFiles;
use Blush\Job\Job;
use Blush\Job\JobRecord;
use Blush\Job\JobResult;
use Blush\Media\AssignedMediaIds;
use Blush\Media\MediaException;
use Blush\Media\MediaIds;
use Blush\Media\MediaSizes;
use Blush\Media\RecordedMediaSizes;

/**
 * Runs a Site Health fix over many files as a job (`blush/health-fix`,
 * D-624), so fixing a whole site's files never outlasts a request. Its
 * data:
 *
 * - `fix`: which (`HealthFix`), and `type` for renaming a type's files.
 * - `remaining`: the paths (media keys, or `{type}/{slug}`s for terms)
 *   still to fix; `null` at first for every file the check finds.
 * - `total`, and what's `changed` and `failed` so far.
 *
 * Each run fixes the next `CHUNK`, as the account that queued it may
 * (checked again on every run, so a role taken away stops it), and
 * hands back the rest. When none are left, its result is what the fix
 * answers in a request: its changes under `HealthFix::changed()`, and
 * `failed`, messages by path.
 */
final class HealthFixJob extends Job
{
	/**
	 * How many files one run fixes.
	 */
	public const int CHUNK = 100;

	public function __construct(
		private readonly Accounts $accounts,
		private readonly FixAccess $access,
		private readonly EntryIds $ids,
		private readonly MediaIds $mediaIds,
		private readonly MediaSizes $mediaSizes,
		private readonly FileNames $fileNames,
		private readonly EntryFolders $folders,
		private readonly MissingTerms $terms,
		private readonly MissingParents $parents,
		private readonly EntryRefs $refs
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): string
	{
		return 'Fix files from Site Health';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function handle(JobRecord $job): JobResult
	{
		$fix     = is_string($job->data['fix'] ?? null) ? HealthFix::tryFrom($job->data['fix']) : null;
		$account = $job->account === null ? null : $this->accounts->findById($job->account);
		$type    = is_string($job->data['type'] ?? null) ? $job->data['type'] : null;

		if ($fix === null) {
			return JobResult::failed('There\'s no such Site Health fix.');
		}

		if ($account === null || $account->suspended) {
			return JobResult::failed('The account that asked for this fix can\'t make it any more.');
		}

		$remaining = self::strings($job->data['remaining'] ?? null) ?? $this->candidates($fix, $type);
		$total     = is_int($job->data['total'] ?? null) ? $job->data['total'] : count($remaining);
		$changed   = is_array($job->data['changed'] ?? null) ? $job->data['changed'] : [];
		$failed    = self::strings($job->data['failed'] ?? null, keyed: true) ?? [];

		[$did, $didFail] = $this->apply($fix, $type, array_slice($remaining, 0, self::CHUNK), $account);

		$changed = $fix === HealthFix::Refs ? array_values([...$changed, ...$did]) : array_replace($changed, $did);
		$failed  = array_replace($failed, $didFail);
		$rest    = array_slice($remaining, self::CHUNK);

		if ($rest !== []) {
			return JobResult::more(
				[...$job->data, 'remaining' => $rest, 'total' => $total, 'changed' => $changed, 'failed' => $failed],
				intdiv(($total - count($rest)) * 100, max(1, $total)),
				sprintf('Fixed %d of %d %s.', $total - count($rest), $total, $fix->noun()[1])
			);
		}

		[$one, $many] = $fix->noun();

		$message = sprintf('Changed %d %s.', count($changed), count($changed) === 1 ? $one : $many)
			. ($failed === [] ? '' : sprintf(' %d couldn\'t be changed.', count($failed)));

		return JobResult::done(
			$message,
			array_map(static fn (string $path, string $why): string => "{$path}: {$why}", array_map(strval(...), array_keys($failed)), $failed),
			[$fix->changed() => $changed, 'failed' => $failed]
		);
	}

	/**
	 * Returns everything the fix's check finds to fix.
	 *
	 * @return list<string>
	 */
	private function candidates(HealthFix $fix, ?string $type): array
	{
		return match ($fix) {
			HealthFix::Ids        => $this->ids->report()->missing,
			HealthFix::MediaIds   => $this->mediaIds->report()->missing,
			HealthFix::MediaSizes => $this->mediaSizes->report()->images(),
			HealthFix::FileNames  => array_map(static fn (FileNameRename $rename): string => $rename->path, $this->fileNames->report()->renames($type)),
			HealthFix::Folders    => array_map(strval(...), array_keys($this->folders->report())),
			HealthFix::Refs       => array_map(strval(...), array_keys($this->refs->report())),
			HealthFix::Terms      => $this->missingTerms(),
			HealthFix::Parents    => $this->missingParents()
		};
	}

	/**
	 * Fixes some files, and returns what changed and what failed.
	 *
	 * @param  list<string> $paths
	 * @return array{array<array-key, mixed>, array<string, string>}
	 */
	private function apply(HealthFix $fix, ?string $type, array $paths, Account $account): array
	{
		$entries = FixAccess::only($this->access->entries($account), $paths);
		$media   = FixAccess::only($this->access->media($account), $paths);

		try {
			$done = match ($fix) {
				HealthFix::Ids        => $this->ids->assignMissing($entries),
				HealthFix::MediaIds   => $this->mediaIds->assignMissing($media),
				HealthFix::MediaSizes => $this->mediaSizes->record($media),
				HealthFix::FileNames  => $this->fileNames->rename($type, $entries),
				HealthFix::Folders    => $this->folders->move($entries),
				HealthFix::Refs       => $this->refs->file($entries),
				HealthFix::Terms      => $this->terms->create($this->access->termTypes($account), $paths),
				HealthFix::Parents    => $this->parents->create($this->access->createTypes($account), $paths)
			};
		} catch (MediaException $e) {
			return [[], array_fill_keys($paths, $e->getMessage())];
		}

		$changes = match (true) {
			$done instanceof AssignedIds, $done instanceof AssignedMediaIds => $done->ids,
			$done instanceof RecordedMediaSizes                             => $done->images,
			$done instanceof RenamedFiles                                   => $done->renamed,
			$done instanceof FiledRefs                                      => $done->paths,
			$done instanceof CreatedEntries                                   => $done->created
		};

		return [$changes, $done->failed];
	}

	/**
	 * Returns every missing parent page's `{type}/{key}`.
	 *
	 * @return list<string>
	 */
	private function missingParents(): array
	{
		$parents = [];

		foreach ($this->parents->report() as $type => $keys) {
			foreach (array_keys($keys) as $key) {
				$parents[] = "{$type}/{$key}";
			}
		}

		return $parents;
	}

	/**
	 * Returns every `{type}/{slug}` entries name with no file.
	 *
	 * @return list<string>
	 */
	private function missingTerms(): array
	{
		$terms = [];

		foreach ($this->terms->report() as $type => $slugs) {
			foreach (array_keys($slugs) as $slug) {
				$terms[] = "{$type}/{$slug}";
			}
		}

		return $terms;
	}

	/**
	 * Returns a list of strings from a job's data (or a map of them, by
	 * string key), or `null` when it isn't one.
	 *
	 * @return ($keyed is true ? ?array<string, string> : ?list<string>)
	 */
	private static function strings(mixed $value, bool $keyed = false): ?array
	{
		if (! is_array($value)) {
			return null;
		}

		$strings = array_filter($value, is_string(...));

		return $keyed ? array_combine(array_map(strval(...), array_keys($strings)), $strings) : array_values($strings);
	}
}
