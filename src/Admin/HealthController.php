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

use Closure;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\Capability;
use Blush\Auth\ContentAction;
use Blush\Auth\Permissions;
use Blush\Content\ContentRepository;
use Blush\Content\EntryIds;
use Blush\Content\Lint\Linter;
use Blush\Content\Writer\AssignedIds;
use Blush\Content\Writer\WriteException;
use Blush\Field\Severity;
use Blush\Field\Violation;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Media\AssignedMediaIds;
use Blush\Media\Index\MediaLibrary;
use Blush\Media\MediaException;
use Blush\Media\MediaIdReport;
use Blush\Media\MediaIds;
use Blush\Media\MediaSizeReport;
use Blush\Media\MediaSizes;

/**
 * Answers `GET {path}/api/health`: the content's problems, as
 * `content:lint` finds them (D-225), media metadata files included
 * (D-293). Errors and warnings by default;
 * `?strict=1` adds notices (undeclared keys, 1.x names, virtual terms).
 * It reads every file, so it runs when asked, not on the dashboard.
 *
 * It lists every file's problems, so it needs to edit anyone's
 * entries of some type.
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
 * Its `mediaIds` do the same for media files (D-487), by their paths
 * under `user/media`: `POST health/media-ids` and `POST
 * health/media-ids/keep` (`{"path"}`), changing only the files the
 * account may edit the details of (`media.edit`, D-407).
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
		private Linter $linter,
		private Permissions $permissions,
		private EntryIds $ids,
		private ContentRepository $content,
		private MediaIds $mediaIds,
		private MediaSizes $mediaSizes,
		private MediaLibrary $library
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, ContentAction::EditOthers)) {
			return Response::json(['error' => 'You aren\'t allowed to see content health.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
		}

		$strict = in_array($request->getQueryParams()['strict'] ?? '', ['1', 'true'], true);
		$report = $this->linter->lint();
		$files  = [];

		foreach ($report->violations($strict ? Severity::Notice : Severity::Warning) as $path => $violations) {
			$files[] = [
				'path'       => (string) $path,
				'violations' => array_map(static fn (Violation $violation): array => [
					'field'    => $violation->field,
					'message'  => $violation->message,
					'severity' => $violation->severity->value
				], $violations)
			];
		}

		$ids = $this->ids->report();

		try {
			$media = $this->mediaIds->report();
			$sizes = $this->mediaSizes->report();
		} catch (MediaException) {
			$media = new MediaIdReport();
			$sizes = new MediaSizeReport();
		}

		return Response::json([
			'checked'  => $report->checked,
			'metadata' => $report->metadata,
			'strict'   => $strict,
			'counts'   => [
				'error'   => $report->count(Severity::Error),
				'warning' => $report->count(Severity::Warning),
				'notice'  => $strict ? $report->count(Severity::Notice) : null
			],
			'files'    => $files,
			'ids'      => self::ids($ids->missing, $ids->duplicates),
			'mediaIds' => self::ids($media->missing, $media->duplicates),
			'mediaSizes' => ['sizes' => $sizes->count(), 'images' => count($sizes->unrecorded), 'stale' => count($sizes->stale)]
		], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Gives each file missing a valid id, that the account may edit, a
	 * new one.
	 */
	public function assign(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, ContentAction::EditOthers)) {
			return Response::json(['error' => 'You aren\'t allowed to fix content.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
		}

		return self::assigned($this->ids->assignMissing($this->editable($account)));
	}

	/**
	 * Keeps a shared id on one file, and gives the others sharing it, that
	 * the account may edit, new ones.
	 */
	public function keep(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, ContentAction::EditOthers)) {
			return Response::json(['error' => 'You aren\'t allowed to fix content.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
		}

		$path = self::path($request);

		if ($path === null) {
			return Response::json(['error' => 'Send a JSON "path": the file that keeps the id.'], Status::BadRequest, ['Cache-Control' => 'no-store']);
		}

		try {
			return self::assigned($this->ids->keep($path, $this->editable($account)));
		} catch (WriteException $e) {
			return Response::json(['error' => $e->getMessage()], Status::UnprocessableContent, ['Cache-Control' => 'no-store']);
		}
	}

	/**
	 * Gives each media file missing a valid id, whose details the account
	 * may edit, a new one.
	 */
	public function assignMedia(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, ContentAction::EditOthers)) {
			return Response::json(['error' => 'You aren\'t allowed to fix media.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
		}

		try {
			return self::assignedMedia($this->mediaIds->assignMissing($this->editableMedia($account)));
		} catch (MediaException $e) {
			return Response::json(['error' => $e->getMessage()], Status::InternalServerError, ['Cache-Control' => 'no-store']);
		}
	}

	/**
	 * Keeps a shared id on one media file, and gives the others sharing
	 * it, whose details the account may edit, new ones.
	 */
	public function keepMedia(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, ContentAction::EditOthers)) {
			return Response::json(['error' => 'You aren\'t allowed to fix media.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
		}

		$path = self::path($request);

		if ($path === null) {
			return Response::json(['error' => 'Send a JSON "path": the media file that keeps the id.'], Status::BadRequest, ['Cache-Control' => 'no-store']);
		}

		try {
			return self::assignedMedia($this->mediaIds->keep($path, $this->editableMedia($account)));
		} catch (MediaException $e) {
			return Response::json(['error' => $e->getMessage()], Status::UnprocessableContent, ['Cache-Control' => 'no-store']);
		}
	}

	/**
	 * Records the sizes of the images, whose details the account may
	 * edit, that don't list them as they are (D-488).
	 */
	public function recordSizes(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, ContentAction::EditOthers)) {
			return Response::json(['error' => 'You aren\'t allowed to fix media.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
		}

		try {
			$recorded = $this->mediaSizes->record($this->editableMedia($account));
		} catch (MediaException $e) {
			return Response::json(['error' => $e->getMessage()], Status::InternalServerError, ['Cache-Control' => 'no-store']);
		}

		return Response::json(['recorded' => (object) $recorded->images, 'failed' => (object) $recorded->failed], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * The `path` a fix sends in its JSON body, or `null`.
	 */
	private static function path(ServerRequestInterface $request): ?string
	{
		try {
			$input = json_decode((string) $request->getBody(), true, 8, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			$input = null;
		}

		$path = is_array($input) ? ($input['path'] ?? null) : null;

		return is_string($path) && $path !== '' ? $path : null;
	}

	/**
	 * Returns whether the account may edit the details of the media file
	 * at a key (D-407).
	 *
	 * @return Closure(string): bool
	 */
	private function editableMedia(Account $account): Closure
	{
		return function (string $key) use ($account): bool {
			$record = $this->library->find($key);

			return $record !== null && $this->permissions->mayChangeMedia($account, Capability::MediaEdit, $record->metadata()->owner);
		};
	}

	/**
	 * Answers which files are missing an id and which ids files share.
	 *
	 * @param  list<string>                $missing
	 * @param  array<string, list<string>> $duplicates
	 * @return array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}
	 */
	private static function ids(array $missing, array $duplicates): array
	{
		return [
			'missing'    => $missing,
			'duplicates' => array_map(
				static fn (string $id, array $paths): array => ['id' => $id, 'paths' => $paths],
				array_map(strval(...), array_keys($duplicates)),
				array_values($duplicates)
			)
		];
	}

	/**
	 * Answers what a media fix did.
	 */
	private static function assignedMedia(AssignedMediaIds $assigned): ResponseInterface
	{
		return Response::json(['assigned' => $assigned->ids, 'failed' => $assigned->failed], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Returns whether the account may edit the entry at a path.
	 *
	 * @return Closure(string): bool
	 */
	private function editable(Account $account): Closure
	{
		return function (string $path) use ($account): bool {
			$entry = $this->content->findPath($path);

			return $entry !== null && $this->permissions->can($account, ContentAction::Edit, $entry);
		};
	}

	/**
	 * Answers what a fix did.
	 */
	private static function assigned(AssignedIds $assigned): ResponseInterface
	{
		return Response::json(['assigned' => $assigned->ids, 'failed' => $assigned->failed], headers: ['Cache-Control' => 'no-store']);
	}
}
