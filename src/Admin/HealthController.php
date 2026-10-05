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
 */
final readonly class HealthController
{
	public function __construct(
		private Linter $linter,
		private Permissions $permissions,
		private EntryIds $ids,
		private ContentRepository $content
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
			'ids'      => [
				'missing'    => $ids->missing,
				'duplicates' => array_map(
					static fn (string $id, array $paths): array => ['id' => $id, 'paths' => $paths],
					array_map(strval(...), array_keys($ids->duplicates)),
					array_values($ids->duplicates)
				)
			]
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

		try {
			$input = json_decode((string) $request->getBody(), true, 8, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			$input = null;
		}

		$path = is_array($input) ? ($input['path'] ?? null) : null;

		if (! is_string($path) || $path === '') {
			return Response::json(['error' => 'Send a JSON "path": the file that keeps the id.'], Status::BadRequest, ['Cache-Control' => 'no-store']);
		}

		try {
			return self::assigned($this->ids->keep($path, $this->editable($account)));
		} catch (WriteException $e) {
			return Response::json(['error' => $e->getMessage()], Status::UnprocessableContent, ['Cache-Control' => 'no-store']);
		}
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
