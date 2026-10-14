<?php

/**
 * Activity controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Http;

use DateTimeImmutable;
use DateTimeInterface;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Activity\Activity;
use Blush\Activity\ActivityAction;
use Blush\Activity\ActivityException;
use Blush\Activity\ActivityFilter;
use Blush\Activity\ActivityLog;
use Blush\Activity\Actor;
use Blush\Activity\Via;
use Blush\Auth\Account;
use Blush\Auth\Accounts;
use Blush\Auth\Capability;
use Blush\Auth\ContentAction;
use Blush\Auth\Permissions;
use Blush\Content\Editing\EditRefused;
use Blush\Content\Editing\EntryEditor;
use Blush\Content\Entries;
use Blush\Content\Writer\WriteException;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Media\Index\MediaLibrary;
use Blush\Media\MediaEditor;
use Blush\Media\MediaException;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * The activity log in the admin (D-758):
 *
 * - `GET activity`: a page of changes, newest first (`changes`, as
 *   `describe()` says each), with `total`, `page`, and `pages`, and the
 *   choices its filters offer (`accounts`, `clients`, `actions`).
 *   Filters: `account` (a username), `via` (`admin`, `mcp`, `cli`, `api`),
 *   `client` (an app's name), `action`, `subject` (an entry's or file's
 *   id), and `page`. The whole log takes `site.activity`; one entry's or
 *   file's (`subject`), as its Document tab shows, takes editing it.
 * - `POST activity/{id}/undo`: puts back what a change replaced, as the
 *   account (`EntryEditor::undo()`, `MediaEditor::undo()`), only while
 *   nothing has changed since; the undo is a change of its own.
 */
final readonly class ActivityController
{
	/**
	 * How many changes a page holds.
	 */
	public const int PER_PAGE = 50;

	public function __construct(
		private ActivityLog $log,
		private Accounts $accounts,
		private Permissions $permissions,
		private Entries $content,
		private MediaLibrary $library,
		private EntryEditor $entries,
		private MediaEditor $media,
		private Messages $messages
	) {}

	public function index(ServerRequestInterface $request): ResponseInterface
	{
		$account = self::account($request);
		$params  = $request->getQueryParams();
		$subject = is_string($params['subject'] ?? null) ? $params['subject'] : null;

		if (! $this->permissions->can($account, Capability::SiteActivity) && ($subject === null || ! $this->mayChange($account, $subject))) {
			return self::json(['error' => $this->messages->t('admin.activity_controller.not_allowed', 'You aren\'t allowed to see the activity log.')], Status::Forbidden);
		}

		$username = is_string($params['account'] ?? null) ? $params['account'] : null;
		$filter   = new ActivityFilter(
			account: $username === null ? null : ($this->accounts->find($username)->id ?? "\0"),
			via: is_string($params['via'] ?? null) ? Via::tryFrom($params['via']) : null,
			client: is_string($params['client'] ?? null) && $params['client'] !== '' ? $params['client'] : null,
			action: is_string($params['action'] ?? null) ? ActivityAction::tryFrom($params['action']) : null,
			subject: $subject
		);

		$page = is_string($params['page'] ?? null) && ctype_digit($params['page']) && (int) $params['page'] >= 1 ? (int) $params['page'] : 1;

		try {
			[$changes, $total] = $this->log->list($filter, self::PER_PAGE, $page);
		} catch (ActivityException $error) {
			return self::json(['error' => $error->getMessage()], Status::InternalServerError);
		}

		$versions = [];

		return self::json([
			'changes'  => array_map(fn (Activity $activity): array => $this->describe($account, $activity, $versions), $changes),
			'total'    => $total,
			'page'     => $page,
			'pages'    => max(1, (int) ceil($total / self::PER_PAGE)),
			'accounts' => $this->accountChoices(),
			'clients'  => $this->clients(),
			'actions'  => array_map(fn (ActivityAction $action): array => ['value' => $action->value, 'label' => $action->label($this->messages)], ActivityAction::cases()),
			'via'      => array_map(fn (Via $via): array => ['value' => $via->value, 'label' => $via->label($this->messages)], Via::cases())
		]);
	}

	public function undo(ServerRequestInterface $request, string $id): ResponseInterface
	{
		$account = self::account($request);

		try {
			$activity = $this->log->find($id);

			if ($activity === null) {
				return self::json(['error' => $this->messages->t('admin.activity_controller.not_found', 'That change isn\'t in the log.')], Status::NotFound);
			}

			if ($activity->action->isEntry()) {
				$this->entries->undo(Actor::admin($account), $activity);
			} else {
				$this->media->undo(Actor::admin($account), $activity);
			}

			$after = $this->log->find($id);
		} catch (EditRefused $refused) {
			return self::json(['error' => $refused->getMessage()], $refused->refusal->status());
		} catch (ActivityException $error) {
			return self::json(['error' => $error->getMessage()], Status::InternalServerError);
		}

		$versions = [];

		return self::json(['change' => $this->describe($account, $after ?? $activity, $versions)]);
	}

	/**
	 * Describes a change: `id`, `time` (ISO 8601), `summary`, its
	 * subject's `title`, `kind` (`entry` or `media`), and `link` (its
	 * admin screen, or `null` when it's gone), who made it (`account`:
	 * `username` and `name`), how (`via`, and an app's or token's
	 * `client`), and `undo`: whether it can be undone now
	 * (`available`), and if not, `reason`; with `undone`, it was.
	 *
	 * @param  array<string, ?string> $versions Entries' versions, by id, read once each.
	 * @return array<string, mixed>
	 */
	private function describe(Account $viewer, Activity $activity, array &$versions): array
	{
		$by    = $this->accounts->findById($activity->account);
		$entry = $activity->action->isEntry() ? $this->content->find($activity->subject) : null;
		$link  = $entry === null ? null : "/content/{$entry->type->name}/{$entry->id}";

		if (! $activity->action->isEntry()) {
			try {
				$key  = $this->library->findId($activity->subject)?->key;
				$link = $key === null ? null : '/media/' . $key;
			} catch (MediaException) {
				$link = null;
			}
		}

		return [
			'id'      => $activity->id,
			'time'    => new DateTimeImmutable('@' . $activity->time)->format(DateTimeInterface::ATOM),
			'action'  => $activity->action->value,
			'summary' => $activity->summary,
			'title'   => $activity->title,
			'kind'    => $activity->action->isEntry() ? 'entry' : 'media',
			'subject' => $activity->subject,
			'link'    => $link,
			'account' => ['username' => $by->username ?? null, 'name' => match (true) {
				$by !== null                 => $by->name ?? $by->username,
				$activity->account === ''    => $this->messages->t('admin.activity_controller.command_line', 'Someone on the command line'),
				default                      => $this->messages->t('admin.activity_controller.removed_account', 'A removed account')
			}],
			'via'     => $activity->via->value,
			'client'  => $activity->client,
			'undone'  => $activity->undone !== null,
			'undoes'  => $activity->undoes !== null,
			'undo'    => $this->undoable($viewer, $activity, $versions)
		];
	}

	/**
	 * Whether a change can be undone now by the viewer, and why not.
	 *
	 * @param  array<string, ?string> $versions
	 * @return array{available: bool, reason: ?Text}
	 */
	private function undoable(Account $viewer, Activity $activity, array &$versions): array
	{
		$reason = match (true) {
			$activity->undone !== null                                      => $this->messages->t('admin.activity_controller.undo.undone', 'Undone.'),
			$activity->action === ActivityAction::EntryCreate               => $this->messages->t('admin.activity_controller.undo.new_entry', 'A new entry; move it to the trash to undo it.'),
			$activity->action === ActivityAction::MediaUpload               => $this->messages->t('admin.activity_controller.undo.new_file', 'A new file; delete it to undo it.'),
			$activity->before === null                                      => $this->messages->t('admin.activity_controller.undo.expired', 'What it replaced is kept for 30 days, and that\'s passed.'),
			! $this->mayChange($viewer, $activity->subject)                 => $this->messages->t('admin.activity_controller.undo.not_allowed', 'You aren\'t allowed to change it.'),
			$activity->action->isEntry() && $this->changedSince($activity, $versions) => $this->messages->t('admin.activity_controller.undo.changed', 'Changed since; open it to edit.'),
			default                                                         => null
		};

		return ['available' => $reason === null, 'reason' => $reason];
	}

	/**
	 * Whether an entry changed after a change to it was made.
	 *
	 * @param array<string, ?string> $versions
	 */
	private function changedSince(Activity $activity, array &$versions): bool
	{
		if (! array_key_exists($activity->subject, $versions)) {
			$entry = $this->content->find($activity->subject);

			try {
				$versions[$activity->subject] = $entry === null ? null : $this->content->editable($entry)->version;
			} catch (WriteException) {
				$versions[$activity->subject] = null;
			}
		}

		return $activity->revision !== null && $versions[$activity->subject] !== $activity->revision;
	}

	/**
	 * Whether an account may change an entry or file, by its id.
	 */
	private function mayChange(Account $account, string $subject): bool
	{
		$entry = $this->content->find($subject);

		if ($entry !== null) {
			return $this->permissions->can($account, ContentAction::Edit, $entry) || $this->permissions->can($account, ContentAction::Delete, $entry);
		}

		try {
			$record = $this->library->findId($subject);
		} catch (MediaException) {
			return false;
		}

		return $record !== null && $this->permissions->mayChangeMedia($account, Capability::MediaEdit, $record->metadata()->owner);
	}

	/**
	 * The apps the log names, for its filter.
	 *
	 * @return list<string>
	 */
	private function clients(): array
	{
		try {
			return $this->log->clients();
		} catch (ActivityException) {
			return [];
		}
	}

	/**
	 * The accounts the log names, by username, for its filter.
	 *
	 * @return list<array{username: string, name: string}>
	 */
	private function accountChoices(): array
	{
		$choices = [];

		foreach ($this->accounts->all() as $account) {
			$choices[] = ['username' => $account->username, 'name' => $account->name ?? $account->username];
		}

		return $choices;
	}

	private static function account(ServerRequestInterface $request): Account
	{
		$account = $request->getAttribute(Account::class);

		return $account instanceof Account ? $account : throw new LogicException('The activity log needs the Authenticate middleware.');
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, Status $status = Status::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}
}
