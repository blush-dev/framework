<?php

/**
 * Password link notification.
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
use Blush\Auth\Accounts;
use Blush\Auth\AuthConfig;
use Blush\Clock\DateFormat;
use Blush\Core\AppConfig;
use Blush\Notification\MailContent;
use Blush\Notification\Notifiable;
use Blush\Notification\Notification;
use Blush\Translation\CurrentLocale;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Emails an account a link for choosing a password (`core/password-link`,
 * D-312, D-846). The link is made when the email is sent, replacing any
 * the account had, so its token is never kept in a queued job: only the
 * one sent works. `reason` says why:
 *
 * - `invite`: an administrator made the account.
 * - `reset`: an administrator made a new link.
 * - `forgot`: someone asked on the sign-in screen, so the email says
 *   what to do if it wasn't them, and the link lasts an hour
 *   (`AuthConfig::$resetLinkLifetime`).
 *
 * A suspended or token-only account, or a site with the admin off, is
 * sent nothing. One waits in the queue per account at a time.
 */
final class PasswordLinkNotification extends Notification
{
	public function __construct(
		private readonly Accounts $accounts,
		private readonly AuthConfig $auth,
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
		return $this->messages->t('notification.password_link.label', 'Password links');
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
	public function unique(array $data): string
	{
		return 'password-link';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function mail(Notifiable $to, array $data): ?MailContent
	{
		$account = $to->account;

		if ($account === null || $account->suspended || $account->tokenOnly || ! $this->admin->enabled) {
			return null;
		}

		[$account, $token] = $this->accounts->issuePasswordLink($account, ($data['reason'] ?? null) === 'forgot' ? $this->auth->resetLinkLifetime : null);

		$site    = $this->app->name;
		$url     = $this->links->setPassword($account, $token);
		$expires = DateFormat::format(new DateTimeImmutable('@' . ($account->passwordLink->expires ?? 0)), $this->locale->get(), $this->app->timezone(), 'long', 'short');
		$hello   = $this->messages->t('notification.password_link.hello', 'Hi {name},', name: $to->name());

		return match ($data['reason'] ?? null) {
			'invite' => new MailContent($this->messages->t('notification.password_link.invite.subject', 'Your account on {site}', site: $site))
				->line($hello)
				->line($this->messages->t('notification.password_link.invite.body', 'An account was made for you on {site}, with the username {username}. Choose a password to sign in.', site: $site, username: $account->username))
				->action($this->messages->t('notification.password_link.invite.action', 'Choose a Password'), $url)
				->note($this->messages->t('notification.password_link.expires', 'The link works once, until {date}.', date: $expires)),
			'forgot' => new MailContent($this->messages->t('notification.password_link.forgot.subject', 'Reset your password on {site}', site: $site))
				->line($hello)
				->line($this->messages->t('notification.password_link.forgot.body', 'Someone asked to reset the password of {username} on {site}. Choose a new one with this link.', site: $site, username: $account->username))
				->action($this->messages->t('notification.password_link.forgot.action', 'Choose a New Password'), $url)
				->note($this->messages->t('notification.password_link.forgot.note', 'The link works once, until {date}. If you didn\'t ask, ignore this email: your password hasn\'t changed.', date: $expires)),
			default  => new MailContent($this->messages->t('notification.password_link.reset.subject', 'Choose a new password on {site}', site: $site))
				->line($hello)
				->line($this->messages->t('notification.password_link.reset.body', 'An administrator of {site} sent you a link to choose a new password for {username}. Your current password works until you do.', site: $site, username: $account->username))
				->action($this->messages->t('notification.password_link.reset.action', 'Choose a New Password'), $url)
				->note($this->messages->t('notification.password_link.expires', 'The link works once, until {date}.', date: $expires))
		};
	}
}
