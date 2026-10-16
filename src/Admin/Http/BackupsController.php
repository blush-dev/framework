<?php

/**
 * Admin backups controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Http;

use JsonException;
use Throwable;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Blush\Auth\Account;
use Blush\Auth\AccountProfiles;
use Blush\Auth\Accounts;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Backup\Backup;
use Blush\Backup\BackupBusy;
use Blush\Backup\BackupException;
use Blush\Backup\BackupGroup;
use Blush\Backup\BackupConfig;
use Blush\Backup\BackupMaker;
use Blush\Backup\BackupNotices;
use Blush\Backup\BackupParts;
use Blush\Backup\BackupRestorer;
use Blush\Backup\BackupSchedule;
use Blush\Backup\Backups;
use Blush\Backup\BackupStatus;
use Blush\Backup\BackupTaken;
use Blush\Core\AppConfig;
use Blush\Core\Paths;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Settings\InvalidSetting;
use Blush\Settings\Setting;
use Blush\Settings\Settings;
use Blush\Settings\SettingsStore;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * The site's backups on Tools → Backups (D-881 to D-886). Everything
 * needs `site.backups` (D-884):
 *
 * - `GET {path}/api/backups`: `{"backups", "parts", "groups", "used",
 *   "free", "folder"}`: every backup, newest first (each `{"name",
 *   "status", "kind", "created", "by", "note", "parts", "size",
 *   "reason", "job"}`, `by` the account's name or `null`), each part a
 *   backup can hold with what it holds now (`{"name", "group", "label",
 *   "description", "count", "bytes"}`), the groups in order, the bytes
 *   backups take and free on their disk, their folder, and the restore
 *   running (`{"job", "backup", "parts"}`, or `null`).
 * - `POST {path}/api/backups`: `{"parts", "note"}` starts one, as a job:
 *   `201` with `{"backup"}`; a `409` while one is being made, a `422`
 *   for parts that aren't.
 * - `PATCH {path}/api/backups/{name}`: `{"note"}` changes its note.
 * - `DELETE {path}/api/backups/{name}`: deletes it (`{"deleted":
 *   true}`), or cancels one being made (`{"canceled": true}`).
 * - `POST {path}/api/backups/{name}/undelete`: puts back one deleted in
 *   the last day, for Undo.
 * - `GET {path}/api/backups/{name}/download`: the `.zip`, streamed.
 * - `POST {path}/api/backups/{name}/check`: `{"parts"}` answers with what
 *   refuses restoring them (`problems`) and what it will do (`warnings`).
 * - `POST {path}/api/backups/{name}/restore`: `{"parts"}` starts
 *   restoring them as a job, the site backed up first: `202` with
 *   `{"job"}`; a `409` while a backup is being made or restored, a `422`
 *   for a problem. Both also need `site.backups.restore`.
 * - `POST {path}/api/backups/upload`: adds an uploaded backup (`file`):
 *   `201` with `{"backup"}`, a `409` when one has its name, a `422` when
 *   it isn't a backup. `GET backups` has `upload`: `{"limit"}`, the
 *   largest file the server takes, or `null`.
 * - `PUT {path}/api/backups/schedule`: saves the schedule (D-887) and
 *   answers with it; `POST backups/schedule/test` sends the test email,
 *   and `POST backups/schedule/preview` answers with an email it would
 *   send. `GET backups` has it as `schedule`, with `next`, `timezone`,
 *   `siteEmail`, and `canMail`.
 *
 * A backup's `by` is its account's display name (D-370).
 */
final readonly class BackupsController
{
	public function __construct(
		private Backups $backups,
		private BackupMaker $maker,
		private BackupParts $parts,
		private BackupRestorer $restorer,
		private BackupNotices $notices,
		private BackupConfig $config,
		private SettingsStore $settings,
		private AppConfig $app,
		private ClockInterface $clock,
		private Accounts $accounts,
		private AccountProfiles $profiles,
		private Permissions $permissions,
		private Paths $paths,
		private Messages $messages
	) {}

	/**
	 * Answers with the backups and the parts.
	 */
	public function index(ServerRequestInterface $request): ResponseInterface
	{
		$refusal = $this->refusal($request);

		if ($refusal !== null) {
			return $refusal;
		}

		$measured = $this->maker->measure();
		$parts    = [];

		foreach ($this->parts->all() as $name => $part) {
			$parts[] = [
				'name'        => $name,
				'group'       => $part->group->value,
				'label'       => (string) $part->label,
				'description' => (string) $part->description,
				'count'       => $measured[$name]['count'] ?? 0,
				'bytes'       => $measured[$name]['bytes'] ?? 0
			];
		}

		return self::json([
			'backups'   => array_map($this->describe(...), $this->backups->all()),
			'parts'     => $parts,
			'groups'    => array_map(fn (BackupGroup $group): array => ['key' => $group->value, 'label' => $group->label($this->messages)], BackupGroup::cases()),
			'used'      => $this->backups->used(),
			'free'      => $this->backups->free(),
			'folder'    => $this->paths->relative($this->backups->folder()),
			'restoring' => $this->restoring(),
			'schedule'  => $this->schedule($this->config->schedule),
			'upload'    => ['limit' => MediaUploadController::limit()]
		]);
	}

	/**
	 * Starts a backup.
	 */
	public function create(ServerRequestInterface $request): ResponseInterface
	{
		$refusal = $this->refusal($request);

		if ($refusal !== null) {
			return $refusal;
		}

		$input = self::input($request);
		$parts = self::parts($request);
		$note  = is_string($input['note'] ?? null) ? $input['note'] : '';

		try {
			$backup = $this->maker->start($parts, $note, self::account($request));
		} catch (BackupBusy $e) {
			return self::error($e->getMessage(), Status::Conflict);
		} catch (BackupException $e) {
			return self::error($e->getMessage(), Status::UnprocessableContent);
		}

		return self::json(['backup' => $this->describe($backup)], Status::Created);
	}

	/**
	 * Changes a backup's note.
	 */
	public function update(ServerRequestInterface $request, string $name): ResponseInterface
	{
		$refusal = $this->refusal($request);
		$backup  = $this->backups->find($name);

		if ($refusal !== null || $backup === null) {
			return $refusal ?? $this->missing($name);
		}

		$note = self::input($request)['note'] ?? null;

		if (! is_string($note)) {
			return self::error($this->messages->t('admin.backups_controller.no_note', 'Send the note as text.'), Status::UnprocessableContent);
		}

		try {
			return self::json(['backup' => $this->describe($this->backups->note($backup, $note))]);
		} catch (BackupException $e) {
			return self::error($e->getMessage(), Status::InternalServerError);
		}
	}

	/**
	 * Deletes a backup, or cancels the one being made.
	 */
	public function delete(ServerRequestInterface $request, string $name): ResponseInterface
	{
		$refusal = $this->refusal($request);
		$backup  = $this->backups->find($name);

		if ($refusal !== null || $backup === null) {
			return $refusal ?? $this->missing($name);
		}

		try {
			$this->backups->delete($backup);
		} catch (BackupException $e) {
			return self::error($e->getMessage(), Status::InternalServerError);
		}

		return self::json($backup->status === BackupStatus::Running ? ['canceled' => true] : ['deleted' => true]);
	}

	/**
	 * Puts back a backup deleted in the last day.
	 */
	public function undelete(ServerRequestInterface $request, string $name): ResponseInterface
	{
		$refusal = $this->refusal($request);

		if ($refusal !== null) {
			return $refusal;
		}

		try {
			return self::json(['backup' => $this->describe($this->backups->restoreDeleted($name))]);
		} catch (BackupException) {
			return self::error($this->messages->t('admin.backups_controller.not_deleted', 'The backup {name} can\'t be put back.', name: $name), Status::NotFound);
		}
	}

	/**
	 * Answers with what refuses restoring parts of a backup, and what it
	 * will do: `{"problems", "warnings"}`.
	 */
	public function check(ServerRequestInterface $request, string $name): ResponseInterface
	{
		$refusal = $this->refusal($request, Capability::SiteRestore);
		$backup  = $this->backups->find($name);

		if ($refusal !== null || $backup === null) {
			return $refusal ?? $this->missing($name);
		}

		$check = $this->restorer->check($backup, self::parts($request));

		return self::json(['problems' => $check->problems, 'warnings' => $check->warnings]);
	}

	/**
	 * Starts restoring parts of a backup, as a job: `202` with `{"job"}`,
	 * its id, to follow.
	 */
	public function restore(ServerRequestInterface $request, string $name): ResponseInterface
	{
		$refusal = $this->refusal($request, Capability::SiteRestore);
		$backup  = $this->backups->find($name);

		if ($refusal !== null || $backup === null) {
			return $refusal ?? $this->missing($name);
		}

		try {
			$job = $this->restorer->start($backup, self::parts($request), self::account($request));
		} catch (BackupBusy $e) {
			return self::error($e->getMessage(), Status::Conflict);
		} catch (BackupException $e) {
			return self::error($e->getMessage(), Status::UnprocessableContent);
		}

		return self::json(['job' => $job->id], Status::Accepted);
	}

	/**
	 * Adds an uploaded backup (the multipart field `file`) to the list:
	 * `201` with `{"backup"}`. One that isn't a backup is a `422`, one
	 * with a kept backup's name a `409`, and nothing is kept either way.
	 */
	public function upload(ServerRequestInterface $request): ResponseInterface
	{
		$refusal = $this->refusal($request);

		if ($refusal !== null) {
			return $refusal;
		}

		$file  = $request->getUploadedFiles()['file'] ?? null;
		$limit = MediaUploadController::limit();

		if (! $file instanceof UploadedFileInterface || $file->getError() !== UPLOAD_ERR_OK || ($limit !== null && ($file->getSize() ?? 0) > $limit)) {
			return self::error($limit === null
				? $this->messages->t('admin.backups_controller.no_file', 'No file arrived. It may be larger than the server takes.')
				: $this->messages->t('admin.backups_controller.too_large', 'No file arrived, or it\'s larger than the server takes (up to {size} MB).', size: intdiv($limit, 1024 * 1024)), Status::UnprocessableContent);
		}

		$temporary = "{$this->backups->folder()}/." . bin2hex(random_bytes(8)) . '.upload';

		try {
			$this->backups->ensureFolder();
			$file->moveTo($temporary);

			return self::json(['backup' => $this->describe($this->backups->upload($temporary))], Status::Created);
		} catch (BackupTaken $e) {
			return self::error($e->getMessage(), Status::Conflict);
		} catch (BackupException $e) {
			return self::error($this->messages->t('admin.backups_controller.not_a_backup', 'Nothing was added: {reason}', reason: $e->getMessage()), Status::UnprocessableContent);
		} catch (Throwable $e) {
			return self::error($e->getMessage(), Status::InternalServerError);
		} finally {
			@unlink($temporary);
		}
	}

	/**
	 * Saves the schedule (D-887), as the `backups.schedule` setting, and
	 * answers with it as `GET backups` has it.
	 */
	public function saveSchedule(ServerRequestInterface $request): ResponseInterface
	{
		$refusal = $this->refusal($request);

		if ($refusal !== null) {
			return $refusal;
		}

		try {
			$saved = $this->settings->update(fn (Settings $settings): Settings => $settings->with([Setting::Backups->value => self::input($request)], $this->messages));
			$value = $saved->get(Setting::Backups);

			return self::json(['schedule' => $this->schedule(BackupSchedule::fromArray(is_array($value) ? $value : []))]);
		} catch (InvalidSetting $e) {
			return self::error($e->getMessage(), Status::UnprocessableContent);
		} catch (Throwable $e) {
			return self::error($e->getMessage(), Status::InternalServerError);
		}
	}

	/**
	 * Sends the schedule's test email, to `{"to"}` or whoever the schedule
	 * emails: `{"sent": true}`, or a `422` when the site can't send mail.
	 */
	public function testEmail(ServerRequestInterface $request): ResponseInterface
	{
		$refusal = $this->refusal($request);

		if ($refusal !== null) {
			return $refusal;
		}

		$to = self::address($request);

		if ($to === false) {
			return self::error($this->messages->t('admin.backups_controller.bad_address', 'That isn\'t an email address. Check it for a typo.'), Status::UnprocessableContent);
		}

		if (! $this->notices->canMail() || ! $this->notices->test($to)) {
			return self::error($this->messages->t('admin.backups_controller.no_mail', 'The site can\'t send email yet. Set up how it sends mail, then try again.'), Status::UnprocessableContent);
		}

		return self::json(['sent' => true, 'to' => $this->notices->recipient($to)->value]);
	}

	/**
	 * Answers with an email the schedule would send (`{"stage"}`: `done`
	 * or `failed`, to `{"to"}`), as `{"from", "to", "subject", "text"}`.
	 */
	public function previewEmail(ServerRequestInterface $request): ResponseInterface
	{
		$refusal = $this->refusal($request);

		if ($refusal !== null) {
			return $refusal;
		}

		$to      = self::address($request);
		$stage   = self::input($request)['stage'] ?? 'done';
		$preview = $to === false ? null : $this->notices->preview(is_string($stage) ? $stage : 'done', $to);

		return $preview === null
			? self::error($this->messages->t('admin.backups_controller.no_preview', 'The email couldn\'t be previewed.'), Status::UnprocessableContent)
			: self::json(['email' => $preview]);
	}

	/**
	 * Answers with a backup's archive, to save.
	 */
	public function download(ServerRequestInterface $request, string $name): ResponseInterface
	{
		$refusal = $this->refusal($request);

		if ($refusal !== null) {
			return $refusal;
		}

		$backup  = $this->backups->find($name);
		$archive = $backup === null ? null : $this->backups->archive($backup);

		if ($archive === null || ! is_readable($archive)) {
			return $this->missing($name);
		}

		return Response::file($archive, 'application/zip', [
			'Content-Disposition' => sprintf('attachment; filename="%s"', basename($archive)),
			'Cache-Control'       => 'no-store'
		]);
	}

	/**
	 * Returns a backup as the admin shows it.
	 *
	 * @return array<string, mixed>
	 */
	private function describe(Backup $backup): array
	{
		$account = $backup->account === null ? null : $this->accounts->findById($backup->account);

		return [
			'name'    => $backup->name,
			'file'    => "{$backup->name}.zip",
			'status'  => $backup->status->value,
			'kind'    => $backup->kind->value,
			'created' => gmdate('c', $backup->created),
			'by'      => $account === null ? null : $this->profiles->displayName($account),
			'note'    => $backup->note,
			'parts'   => $backup->partNames(),
			'size'    => $backup->size,
			'reason'  => $backup->reason,
			'job'     => $backup->job
		];
	}

	/**
	 * Returns a refusal when the account may not see backups, or `null`.
	 */
	private function refusal(ServerRequestInterface $request, ?Capability $also = null): ?ResponseInterface
	{
		$account = self::account($request);

		if ($account === null) {
			return self::error($this->messages->t('admin.common.sign_in_first', 'Sign in first.'), Status::Unauthorized);
		}

		if (! $this->permissions->can($account, Capability::SiteBackups)) {
			return self::error($this->messages->t('admin.backups_controller.not_allowed', 'You aren\'t allowed to see the site\'s backups.'), Status::Forbidden);
		}

		return $also === null || $this->permissions->can($account, $also)
			? null
			: self::error($this->messages->t('admin.backups_controller.no_restore', 'You aren\'t allowed to restore backups.'), Status::Forbidden);
	}

	/**
	 * Returns the restore running, as `{"job", "backup", "parts"}`, or
	 * `null`.
	 *
	 * @return array{job: string, backup: mixed, parts: mixed}|null
	 */
	private function restoring(): ?array
	{
		$job = $this->restorer->running();

		return $job === null ? null : ['job' => $job->id, 'backup' => $job->data['backup'] ?? null, 'parts' => $job->data['parts'] ?? []];
	}

	/**
	 * Returns the schedule as the admin edits it, with when it next runs
	 * (in the site's time zone), the site's time zone and email address,
	 * and whether the site can send mail.
	 *
	 * @return array<string, mixed>
	 */
	private function schedule(BackupSchedule $schedule): array
	{
		$when = $schedule->when();
		$next = null;

		try {
			$next = $when?->next($this->clock->now()->setTimezone($this->app->timezone()))->format('c');
		} catch (Throwable) {
			// A schedule that can't say when it runs says it doesn't.
		}

		return [
			...$schedule->toArray(),
			'next'      => $next,
			'timezone'  => $this->app->timezone()->getName(),
			'siteEmail' => $this->app->email,
			'canMail'   => $this->notices->canMail()
		];
	}

	/**
	 * Returns the address a request names (`to`), `null` for none, or
	 * `false` for one that isn't an address.
	 */
	private static function address(ServerRequestInterface $request): string|false|null
	{
		$to = self::input($request)['to'] ?? null;

		if (! is_string($to) || trim($to) === '') {
			return null;
		}

		return Account::isValidEmail(trim($to)) ? trim($to) : false;
	}

	/**
	 * Returns the parts a request names.
	 *
	 * @return list<string>
	 */
	private static function parts(ServerRequestInterface $request): array
	{
		$parts = self::input($request)['parts'] ?? null;

		return is_array($parts) ? array_values(array_filter($parts, is_string(...))) : [];
	}

	/**
	 * Returns the answer for a backup that isn't there.
	 */
	private function missing(string $name): ResponseInterface
	{
		return self::error($this->messages->t('admin.backups_controller.missing', 'There\'s no backup named {name}.', name: $name), Status::NotFound);
	}

	/**
	 * Returns the signed-in account, or `null`.
	 */
	private static function account(ServerRequestInterface $request): ?Account
	{
		$account = $request->getAttribute(Account::class);

		return $account instanceof Account ? $account : null;
	}

	/**
	 * Returns the request's JSON body, or an empty one.
	 *
	 * @return array<mixed>
	 */
	private static function input(ServerRequestInterface $request): array
	{
		try {
			$input = json_decode((string) $request->getBody(), true, 16, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return [];
		}

		return is_array($input) ? $input : [];
	}

	/**
	 * Returns a JSON answer the browser won't cache.
	 *
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, Status $status = Status::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}

	/**
	 * Returns an error answer.
	 */
	private static function error(string|Text $message, Status $status): ResponseInterface
	{
		return self::json(['error' => $message], $status);
	}
}
