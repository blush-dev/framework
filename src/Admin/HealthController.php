<?php

/**
 * Admin content health controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use DateTimeInterface;
use Psr\Clock\ClockInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\AccountStore;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Content\EntryIds;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\InvalidContentType;
use Blush\Content\Type\TaxonomyMigration;
use Blush\Content\Writer\AssignedIds;
use Blush\Content\Writer\WriteException;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Job\JobException;
use Blush\Job\JobQueue;
use Blush\Job\JobType;
use Blush\Media\AssignedMediaIds;
use Blush\Media\MediaException;
use Blush\Media\MediaIds;

/**
 * Answers `GET {path}/api/health`: the content and media files' problems
 * (`ContentHealth`, notices included), for Site Health's detail screens
 * (D-543), as Site Health's last report has them (D-546); `POST
 * {path}/api/health` checks them again, reading every file, as a job a
 * chunk at a time (`HealthCheckJob`, D-625), answering `{"job": id}`
 * for the admin to run and follow and then ask for them again (or at
 * once, answering them, before there's a report).
 *
 * It and every fix need `site.health` (D-543); a fix changes only the
 * files the account may edit. Checking again updates Site Health's
 * report too (D-545), so the admin, which checks again after a fix,
 * shows the fix there as well.
 *
 * Its `ids` say which files are missing a valid id and which ids files
 * share (D-477), for fixing here (D-478):
 *
 * - `POST health/ids`: gives each file missing a valid id a new one.
 * - `POST health/ids/keep` (`{"path"}`): keeps a shared id on that file
 *   and gives the other files sharing it new ones.
 *
 * Each changes only the files the account may edit, and answers the
 * `assigned` ids by path and the files that `failed`, with why.
 *
 * Its `ignored` are the problems ignored (D-613), by key (`ProblemKeys`),
 * each with who ignored it (`by`, a username, and `name`, as shown) and
 * when (`at`). `POST health/ignore` and `POST health/unignore` (`{"key"}`)
 * ignore a problem for everyone and stop, answering `ignored`; Site
 * Health's summary leaves ignored problems out. The admin never offers
 * to ignore an error, since the site is already leaving something out.
 *
 * The fixes that change many files (`HealthFix`: ids, media ids, media
 * sizes, file names, folders, terms, and refs) run as a job (D-624):
 * each answers `{"job": id}`, which the admin runs and follows
 * (`JobController`), and the finished job's `result` is the answer
 * described below. Keeping a shared id and migrating taxonomies run in
 * the request.
 *
 * Every fix but `keep` and the taxonomies' takes a JSON `paths` (a list,
 * media files' by their keys) to change only those, one row or a page
 * of rows on Site Health (D-612); without it, it changes every file the
 * check found. `POST health/terms` takes `terms` instead, each
 * `{type}/{slug}`.
 *
 * Its `mediaIds` do the same for media files (D-487), by their paths
 * under `user/media`: `POST health/media-ids` and `POST
 * health/media-ids/keep` (`{"path"}`), changing only the files the
 * account may edit the details of (`media.edit`, D-407).
 *
 * Its `fileNames` list, by type, the entries named by another pattern
 * than the type's `filename` (D-511, D-512, D-514): its `type`, `label`,
 * and `pattern`, how many to rename (`count`), the first few renames
 * (`examples`, each `path` and `to`), and how many it leaves as they
 * are (`skipped`, kept as folders). `POST health/filenames` (`{"type"}`)
 * renames a type's, those the account may edit, answering the new paths
 * by old (`renamed`) and `failed`.
 *
 * Its `folders` says how many collections' and profiles' files aren't
 * in the folders their type keeps them in (`count`, D-514, D-629) and
 * each move (`items`). `POST health/folders` moves those the account may edit,
 * answering the new paths by old (`renamed`) and `failed`.
 *
 * Its `terms` say how many terms and profiles entries name have no file
 * (`count`, D-584) and the first few (`examples`, each `type`, `slug`,
 * and the `title` its file gets). `POST health/terms` writes a published
 * file for each, of the types the account may create and publish,
 * answering the new paths by `{type}/{slug}` (`created`) and `failed`.
 *
 * Its `refs` say how many files have links between entries not filed
 * with their ids (`count`, D-596) and the first few (`examples`, each
 * `path` and the `relations` that differ). `POST health/refs` files
 * those the account may edit, answering the paths written (`filed`) and
 * `failed`.
 *
 * Its `taxonomies` name the data types still written as taxonomies
 * (D-591). `POST health/taxonomies` migrates them to collections and
 * classify relations (D-593), for accounts that may also change content
 * types (`site.settings`), answering the files written by type
 * (`migrated`) and `failed`.
 *
 * Its `mediaSizes` say how many images' sizes aren't recorded in their
 * metadata files (D-488): `sizes` and the `images` they're of, and
 * `stale` (images listing files that aren't their sizes). `POST health/media-sizes`
 * records them, for the images the account may edit the details of,
 * answering the sizes now listed by image (`recorded`) and `failed`.
 */
final readonly class HealthController
{
	public function __construct(
		private SiteHealth $siteHealth,
		private Permissions $permissions,
		private EntryIds $ids,
		private MediaIds $mediaIds,
		private ContentTypes $types,
		private TaxonomyMigration $taxonomies,
		private FixAccess $access,
		private JobQueue $jobs,
		private IgnoredProblems $ignored,
		private AccountStore $accounts,
		private ClockInterface $clock
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return Response::json(['error' => 'You aren\'t allowed to see site health.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
		}

		return Response::json([...$this->siteHealth->files($request->getServerParams()), 'ignored' => $this->ignoredList()], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Checks content and media files again (D-546), as after a fix, and
	 * answers them.
	 */
	public function check(ServerRequestInterface $request): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return Response::json(['error' => 'You aren\'t allowed to see site health.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
		}

		$account = $request->getAttribute(Account::class);

		if (! $this->siteHealth->hasFiles() || ! $account instanceof Account) {
			return Response::json([...$this->siteHealth->checkFiles($request->getServerParams()), 'ignored' => $this->ignoredList()], headers: ['Cache-Control' => 'no-store']);
		}

		try {
			$job = $this->jobs->push(JobType::HealthCheck->value, account: $account->username, unique: JobType::HealthCheck->value);
		} catch (JobException $e) {
			return Response::json(['error' => $e->getMessage()], Status::InternalServerError, ['Cache-Control' => 'no-store']);
		}

		return Response::json(['job' => $job->id], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Ignores a problem for everyone (D-613), and sums up Site Health
	 * again without it.
	 */
	public function ignore(ServerRequestInterface $request): ResponseInterface
	{
		return $this->changeIgnored($request, true);
	}

	/**
	 * Stops ignoring a problem (D-613).
	 */
	public function unignore(ServerRequestInterface $request): ResponseInterface
	{
		return $this->changeIgnored($request, false);
	}

	private function changeIgnored(ServerRequestInterface $request, bool $ignore): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, Capability::SiteHealth)) {
			return Response::json(['error' => 'You aren\'t allowed to change site health.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
		}

		$key = self::input($request, 'key');

		if ($key === null) {
			return Response::json(['error' => 'Send a JSON "key": the problem\'s.'], Status::BadRequest, ['Cache-Control' => 'no-store']);
		}

		if ($ignore) {
			$this->ignored->ignore($key, $account->username, $this->clock->now()->format(DateTimeInterface::ATOM));
		} else {
			$this->ignored->unignore($key);
		}

		$this->siteHealth->resummarize();

		return Response::json(['ignored' => $this->ignoredList()], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * The problems ignored, by key, with who ignored each, by name.
	 *
	 * @return array<string, array{by: string, name: string, at: string}>|object
	 */
	private function ignoredList(): array|object
	{
		$list = [];

		foreach ($this->ignored->all() as $key => $record) {
			$account    = $this->accounts->find($record['by']);
			$list[$key] = [...$record, 'name' => ($account === null ? null : $account->name) ?? $record['by']];
		}

		return $list === [] ? (object) [] : $list;
	}

	/**
	 * Gives each file missing a valid id, that the account may edit, a
	 * new one, as a job.
	 */
	public function assign(ServerRequestInterface $request): ResponseInterface
	{
		return $this->queue($request, HealthFix::Ids);
	}

	/**
	 * Keeps a shared id on one file, and gives the others sharing it, that
	 * the account may edit, new ones.
	 */
	public function keep(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, Capability::SiteHealth)) {
			return Response::json(['error' => 'You aren\'t allowed to fix content.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
		}

		$path = self::path($request);

		if ($path === null) {
			return Response::json(['error' => 'Send a JSON "path": the file that keeps the id.'], Status::BadRequest, ['Cache-Control' => 'no-store']);
		}

		try {
			return self::assigned($this->ids->keep($path, $this->access->entries($account)));
		} catch (WriteException $e) {
			return Response::json(['error' => $e->getMessage()], Status::UnprocessableContent, ['Cache-Control' => 'no-store']);
		}
	}

	/**
	 * Gives each media file missing a valid id, whose details the account
	 * may edit, a new one, as a job.
	 */
	public function assignMedia(ServerRequestInterface $request): ResponseInterface
	{
		return $this->queue($request, HealthFix::MediaIds);
	}

	/**
	 * Keeps a shared id on one media file, and gives the others sharing
	 * it, whose details the account may edit, new ones.
	 */
	public function keepMedia(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, Capability::SiteHealth)) {
			return Response::json(['error' => 'You aren\'t allowed to fix media.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
		}

		$path = self::path($request);

		if ($path === null) {
			return Response::json(['error' => 'Send a JSON "path": the media file that keeps the id.'], Status::BadRequest, ['Cache-Control' => 'no-store']);
		}

		try {
			return self::assignedMedia($this->mediaIds->keep($path, $this->access->media($account)));
		} catch (MediaException $e) {
			return Response::json(['error' => $e->getMessage()], Status::UnprocessableContent, ['Cache-Control' => 'no-store']);
		}
	}

	/**
	 * Records the sizes of the images, whose details the account may
	 * edit, that don't list them as they are (D-488), as a job.
	 */
	public function recordSizes(ServerRequestInterface $request): ResponseInterface
	{
		return $this->queue($request, HealthFix::MediaSizes);
	}

	/**
	 * Renames a type's entries, those the account may edit, to its file
	 * name pattern (D-512), as a job.
	 */
	public function renameFiles(ServerRequestInterface $request): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return Response::json(['error' => 'You aren\'t allowed to fix files.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
		}

		$type = self::input($request, 'type');

		if ($type === null || $this->types->find($type) === null) {
			return Response::json(['error' => 'Send a JSON "type": the type whose entries to rename.'], Status::BadRequest, ['Cache-Control' => 'no-store']);
		}

		return $this->queue($request, HealthFix::FileNames, $type);
	}

	/**
	 * Moves collections' and profiles' files that aren't in their folders,
	 * those the account may edit, into them (D-514, D-629), as a job.
	 */
	public function folders(ServerRequestInterface $request): ResponseInterface
	{
		return $this->queue($request, HealthFix::Folders);
	}

	/**
	 * Writes a file for each term and profile entries name with no file
	 * (D-584), of the types the account may create and publish, as a job.
	 */
	public function createTerms(ServerRequestInterface $request): ResponseInterface
	{
		return $this->queue($request, HealthFix::Terms);
	}

	/**
	 * Files both forms of the links between entries (D-589, D-596) in the
	 * files the account may edit, as a job.
	 */
	public function fileRefs(ServerRequestInterface $request): ResponseInterface
	{
		return $this->queue($request, HealthFix::Refs);
	}

	/**
	 * Migrates the data types still written as taxonomies (D-591), for an
	 * account that may change content types.
	 */
	public function migrateTaxonomies(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, Capability::SiteHealth) || ! $this->permissions->can($account, Capability::SiteSettings)) {
			return Response::json(['error' => 'You aren\'t allowed to change content types.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
		}

		try {
			$done = $this->taxonomies->migrate();
		} catch (InvalidContentType $error) {
			return Response::json(['error' => $error->getMessage()], Status::UnprocessableContent, ['Cache-Control' => 'no-store']);
		}

		return Response::json(['migrated' => (object) $done['migrated'], 'failed' => (object) $done['failed']], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Queues a fix as a job (`HealthFixJob`, D-624), for the request's
	 * account, over the `paths` (or `terms`) it sent, else every file the
	 * check finds, and answers its id, which the admin runs and follows.
	 */
	private function queue(ServerRequestInterface $request, HealthFix $fix, ?string $type = null): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, Capability::SiteHealth)) {
			return Response::json(['error' => 'You aren\'t allowed to fix files.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
		}

		$paths = self::list($request, $fix === HealthFix::Terms ? 'terms' : 'paths');

		try {
			$job = $this->jobs->push(JobType::HealthFix->value, ['fix' => $fix->value, 'type' => $type, 'remaining' => $paths], $account->username);
		} catch (JobException $e) {
			return Response::json(['error' => $e->getMessage()], Status::InternalServerError, ['Cache-Control' => 'no-store']);
		}

		return Response::json(['job' => $job->id], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Whether the request's account may see site health (D-543).
	 */
	private function allowed(ServerRequestInterface $request): bool
	{
		$account = $request->getAttribute(Account::class);

		return $account instanceof Account && $this->permissions->can($account, Capability::SiteHealth);
	}

	/**
	 * The `path` a fix sends in its JSON body, or `null`.
	 */
	private static function path(ServerRequestInterface $request): ?string
	{
		return self::input($request, 'path');
	}

	/**
	 * A list of strings a fix sends in its JSON body, or `null`.
	 *
	 * @return ?list<string>
	 */
	private static function list(ServerRequestInterface $request, string $key): ?array
	{
		$value = self::decode($request)[$key] ?? null;

		return is_array($value) ? array_values(array_filter($value, is_string(...))) : null;
	}

	/**
	 * The JSON body a fix sends, or an empty array.
	 *
	 * @return array<array-key, mixed>
	 */
	private static function decode(ServerRequestInterface $request): array
	{
		try {
			$input = json_decode((string) $request->getBody(), true, 8, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			$input = null;
		}

		return is_array($input) ? $input : [];
	}

	/**
	 * A string a fix sends in its JSON body, or `null`.
	 */
	private static function input(ServerRequestInterface $request, string $key): ?string
	{
		$value = self::decode($request)[$key] ?? null;

		return is_string($value) && $value !== '' ? $value : null;
	}

	/**
	 * Answers what a media fix did.
	 */
	private static function assignedMedia(AssignedMediaIds $assigned): ResponseInterface
	{
		return Response::json(['assigned' => $assigned->ids, 'failed' => $assigned->failed], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Answers what a fix did.
	 */
	private static function assigned(AssignedIds $assigned): ResponseInterface
	{
		return Response::json(['assigned' => $assigned->ids, 'failed' => $assigned->failed], headers: ['Cache-Control' => 'no-store']);
	}
}
