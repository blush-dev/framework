<?php

/**
 * Email change notification.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Notification\Notifications;

use DateTimeImmutable;
use Override;
use Blush\Admin\AdminConfig;
use Blush\Admin\AdminLinks;
use Blush\Auth\Account;
use Blush\Auth\Accounts;
use Blush\Auth\EmailChanges;
use Blush\Clock\DateFormat;
use Blush\Core\AppConfig;
use Blush\Notification\MailContent;
use Blush\Notification\Notifiable;
use Blush\Notification\Notification;
use Blush\Translation\CurrentLocale;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Emails about a change of email address (`core/email-change`, D-849),
 * the site's (`for` is `site`) or an account's (`for` is its id), by
 * `stage`:
 *
 * - `confirm`, to the new address: a link that confirms it, made when
 *   the email is sent (`EmailChanges::issue()`), so its token is never
 *   kept in a queued job. Nothing is sent once the change no longer
 *   waits on that address.
 * - `asked`, to the old address: a change was asked for, and what to do
 *   if it wasn't them.
 * - `changed`, to the old address: it changed.
 *
 * `email` is the new address, `old` the old, and `by` the account that
 * asked or changed it, when it wasn't the account itself. Mail about an
 * account is written in its language (D-831). With the admin off, no
 * link can be followed, so `confirm` sends nothing.
 */
final class EmailChangeNotification extends Notification
{
	public function __construct(
		private readonly EmailChanges $changes,
		private readonly Accounts $accounts,
		private readonly AdminLinks $links,
		private readonly AdminConfig $admin,
		private readonly AppConfig $app,
		private readonly CurrentLocale $locale,
		private readonly Messages $messages
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): Text
	{
		return $this->messages->t('notification.email_change.label', 'Email address changes');
	}

	/**
	 * Someone is waiting on the link, so it's sent right after the
	 * request that asked for it (D-847).
	 */
	#[Override]
	public function isUrgent(): bool
	{
		return true;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function unique(array $data): ?string
	{
		return ($data['stage'] ?? null) === 'confirm' && is_string($data['for'] ?? null) ? "email-change:{$data['for']}" : null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function mail(Notifiable $to, array $data): ?MailContent
	{
		$stage = $data['stage'] ?? null;
		$for   = $data['for'] ?? null;
		$email = $data['email'] ?? null;
		$old   = $data['old'] ?? null;

		if (! is_string($stage) || ! is_string($for) || ! is_string($email) || ! is_string($old)) {
			return null;
		}

		$by = is_string($data['by'] ?? null) ? $this->accounts->findById($data['by']) : null;

		if ($for === EmailChanges::SITE) {
			return $this->site($stage, $email, $old, $by);
		}

		$account = $this->accounts->findById($for);

		if ($account === null || $account->suspended) {
			return null;
		}

		return $this->locale->in($account->preferences->language, fn (): ?MailContent => $this->account($stage, $account, $email, $by));
	}

	/**
	 * The email about a change of the site's address.
	 */
	private function site(string $stage, string $email, string $old, ?Account $by): ?MailContent
	{
		$site = $this->app->name;
		$name = $by === null ? $this->messages->t('notification.email_change.someone', 'Someone') : ($by->name ?? $by->username);

		if ($stage === 'confirm') {
			$url = $this->confirmUrl(EmailChanges::SITE, $email);

			return $url === null ? null : new MailContent($this->messages->t('notification.email_change.site.confirm.subject', 'Confirm the new email address of {site}', site: $site))
				->line($this->messages->t('notification.email_change.hello_anyone', 'Hello,'))
				->line($this->messages->t('notification.email_change.site.confirm.body', '{name} asked to make this the email address of {site}, where it sends notices for its owner. Confirm it with this link; until you do, notices go to {old}.', name: $name, site: $site, old: $old))
				->action($this->messages->t('notification.email_change.confirm_action', 'Confirm Email Address'), $url)
				->note($this->messages->t('notification.email_change.confirm_note', 'The link works once, until {date}. If you didn\'t expect this, ignore this email: nothing changes.', date: $this->expires(EmailChanges::SITE)));
		}

		if ($stage === 'asked') {
			return new MailContent($this->messages->t('notification.email_change.site.asked.subject', 'The email address of {site} is changing', site: $site))
				->line($this->messages->t('notification.email_change.hello_anyone', 'Hello,'))
				->line($this->messages->t('notification.email_change.site.asked.body', '{name} asked to change the email address of {site} from this one to {email}. It changes once that address confirms it.', name: $name, site: $site, email: $email))
				->note($this->messages->t('notification.email_change.site.asked.note', 'If you didn\'t expect this, sign in and change the address back on Settings → General before it\'s confirmed.'));
		}

		return new MailContent($this->messages->t('notification.email_change.site.changed.subject', 'The email address of {site} changed', site: $site))
			->line($this->messages->t('notification.email_change.hello_anyone', 'Hello,'))
			->line($this->messages->t('notification.email_change.site.changed.body', 'The email address of {site} is {email} now, so notices for its owner go there instead of here.', site: $site, email: $email))
			->note($this->messages->t('notification.email_change.site.changed.note', 'If you didn\'t expect this, sign in and check Settings → General.'));
	}

	/**
	 * The email about a change of an account's address.
	 */
	private function account(string $stage, Account $account, string $email, ?Account $by): ?MailContent
	{
		$site  = $this->app->name;
		$hello = $this->messages->t('notification.email_change.hello', 'Hi {name},', name: $account->name ?? $account->username);

		if ($stage === 'confirm') {
			$url = $this->confirmUrl($account->id, $email);

			return $url === null ? null : new MailContent($this->messages->t('notification.email_change.account.confirm.subject', 'Confirm your new email address on {site}', site: $site))
				->line($hello)
				->line($this->messages->t('notification.email_change.account.confirm.body', 'You asked to make this the email address of {username} on {site}. Confirm it with this link; until you do, your account keeps the one it has.', username: $account->username, site: $site))
				->action($this->messages->t('notification.email_change.confirm_action', 'Confirm Email Address'), $url)
				->note($this->messages->t('notification.email_change.confirm_note', 'The link works once, until {date}. If you didn\'t expect this, ignore this email: nothing changes.', date: $this->expires($account->id)));
		}

		if ($stage === 'asked') {
			return new MailContent($this->messages->t('notification.email_change.account.asked.subject', 'Your email address on {site} is changing', site: $site))
				->line($hello)
				->line($this->messages->t('notification.email_change.account.asked.body', 'Someone signed in as {username} on {site} asked to change its email address from this one to {email}. It changes once that address confirms it.', username: $account->username, site: $site, email: $email))
				->note($this->messages->t('notification.email_change.account.asked.note', 'If it wasn\'t you, sign in and change your password, then tell the site\'s administrators.'));
		}

		return new MailContent($this->messages->t('notification.email_change.account.changed.subject', 'Your email address on {site} changed', site: $site))
			->line($hello)
			->line($by === null
				? $this->messages->t('notification.email_change.account.changed.body', 'The email address of {username} on {site} is {email} now, so its email goes there instead of here.', username: $account->username, site: $site, email: $email)
				: $this->messages->t('notification.email_change.account.changed.body_by', '{name}, an administrator of {site}, changed the email address of {username} to {email}, so its email goes there instead of here.', name: $by->name ?? $by->username, username: $account->username, site: $site, email: $email))
			->note($this->messages->t('notification.email_change.account.changed.note', 'If you didn\'t expect this, tell the site\'s administrators.'));
	}

	/**
	 * Returns a new confirmation link, or `null` when the change no longer
	 * waits on the address, or the admin is off.
	 */
	private function confirmUrl(string $for, string $email): ?string
	{
		if (! $this->admin->enabled) {
			return null;
		}

		$token = $this->changes->issue($for, $email);

		return $token === null ? null : $this->links->confirmEmail($for, $token);
	}

	/**
	 * Returns when a waiting change's link expires, in words.
	 */
	private function expires(string $for): string
	{
		$expires = $this->changes->pending($for)->expires ?? 0;

		return DateFormat::format(new DateTimeImmutable('@' . $expires), $this->locale->get(), $this->app->timezone(), 'long', 'short');
	}
}
