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
use Blush\Content\FileNameRename;
use Blush\Content\FileNames;
use Blush\Content\FlatEntries;
use Blush\Content\Type\ContentTypes;
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
 * Its `fileNames` list, by type, the entries named by another pattern
 * than the type's `filename` (D-511, D-512, D-514): its `type`, `label`,
 * and `pattern`, how many to rename (`count`), the first few renames
 * (`examples`, each `path` and `to`), and how many it leaves as they
 * are (`skipped`, kept as folders). `POST health/filenames` (`{"type"}`)
 * renames a type's, those the account may edit, answering the new paths
 * by old (`renamed`) and `failed`.
 *
 * Its `flat` says how many collections' files aren't directly in their
 * collection's folder (`count`, D-514) and the first few moves
 * (`examples`). `POST health/flatten` moves those the account may edit,
 * answering the new paths by old (`renamed`) and `failed`.
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
		private MediaLibrary $library,
		private FileNames $fileNames,
		private ContentTypes $types,
		private FlatEntries $flat
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
			'fileNames' => $this->fileNames(),
			'flat'      => $this->flatReport(),
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
	 * Renames a type's entries, those the account may edit, to its file
	 * name pattern (D-512).
	 */
	public function renameFiles(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, ContentAction::EditOthers)) {
			return Response::json(['error' => 'You aren\'t allowed to fix content.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
		}

		$type = self::input($request, 'type');

		if ($type === null || $this->types->find($type) === null) {
			return Response::json(['error' => 'Send a JSON "type": the type whose entries to rename.'], Status::BadRequest, ['Cache-Control' => 'no-store']);
		}

		$renamed = $this->fileNames->rename($type, $this->editable($account));

		return Response::json(['renamed' => (object) $renamed->renamed, 'failed' => (object) $renamed->failed], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Moves collections' files that aren't flat, those the account may
	 * edit, into their collection's folder (D-514).
	 */
	public function flatten(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, ContentAction::EditOthers)) {
			return Response::json(['error' => 'You aren\'t allowed to fix content.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
		}

		$moved = $this->flat->flatten($this->editable($account));

		return Response::json(['renamed' => (object) $moved->renamed, 'failed' => (object) $moved->failed], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Answers how many collections' files aren't flat, and the first few
	 * moves.
	 *
	 * @return array{count: int, examples: list<array{path: string, to: string}>}
	 */
	private function flatReport(): array
	{
		$moves = $this->flat->report();

		return [
			'count'    => count($moves),
			'examples' => array_map(static fn (string $path, string $to): array => ['path' => $path, 'to' => $to], array_slice(array_keys($moves), 0, 3), array_slice(array_values($moves), 0, 3))
		];
	}

	/**
	 * Answers the entries to rename to their type's pattern, by type.
	 *
	 * @return list<array{type: string, label: string, pattern: string, count: int, examples: list<array{path: string, to: string}>, skipped: int}>
	 */
	private function fileNames(): array
	{
		$report = $this->fileNames->report();
		$types  = [];

		foreach (array_keys($report->renames) as $name) {
			$type    = $this->types->find((string) $name);
			$renames = $report->renames((string) $name);

			if ($type === null || $renames === []) {
				continue;
			}

			$types[] = [
				'type'     => $type->name,
				'label'    => $type->labels->plural,
				'pattern'  => $type->naming()->pattern,
				'count'    => count($renames),
				'examples' => array_map(static fn (FileNameRename $rename): array => ['path' => $rename->path, 'to' => $rename->to], array_slice($renames, 0, 3)),
				'skipped'  => count($report->skipped[$type->name] ?? [])
			];
		}

		return $types;
	}

	/**
	 * The `path` a fix sends in its JSON body, or `null`.
	 */
	private static function path(ServerRequestInterface $request): ?string
	{
		return self::input($request, 'path');
	}

	/**
	 * A string a fix sends in its JSON body, or `null`.
	 */
	private static function input(ServerRequestInterface $request, string $key): ?string
	{
		try {
			$input = json_decode((string) $request->getBody(), true, 8, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			$input = null;
		}

		$value = is_array($input) ? ($input[$key] ?? null) : null;

		return is_string($value) && $value !== '' ? $value : null;
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
