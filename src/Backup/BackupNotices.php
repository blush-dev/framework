<?php

/**
 * Backup notices.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Backup;

use Throwable;
use Blush\Auth\Capability;
use Blush\Core\AppConfig;
use Blush\Mail\Address;
use Blush\Mail\MailConfig;
use Blush\Notification\MailLayout;
use Blush\Notification\Notifiable;
use Blush\Notification\NotificationFactory;
use Blush\Notification\NotificationType;
use Blush\Notification\Notifier;
use Blush\Notification\Recipient;

/**
 * Who hears about scheduled backups, and when (D-887): after one that
 * fails, and the first that works after a failure, by default; after
 * every one; or never, by the schedule's `notice`. Mail goes to the
 * schedule's `to`, else the site's email address, else every account
 * that may see backups. Only scheduled backups email: one made by hand
 * finishes on the screen it was made on.
 *
 * Send a Test Email (`test()`) and Preview the Email (`preview()`) are
 * the Schedule form's.
 */
final readonly class BackupNotices
{
	public function __construct(
		private BackupConfig $config,
		private Backups $backups,
		private Notifier $notifier,
		private NotificationFactory $notifications,
		private MailLayout $layout,
		private MailConfig $mail,
		private AppConfig $app
	) {}

	/**
	 * Tells of a scheduled backup that worked, when the schedule asks
	 * for it, or when the one before it failed.
	 */
	public function done(Backup $backup): void
	{
		$notice = $this->config->schedule->notice;

		if ($notice === BackupNotice::Never) {
			return;
		}

		$recovered = $this->previousFailed($backup);

		if ($notice === BackupNotice::Always || $recovered) {
			$this->send(['stage' => $recovered && $notice !== BackupNotice::Always ? 'recovered' : 'done', 'backup' => $backup->name]);
		}
	}

	/**
	 * Tells of a scheduled backup that failed, unless the schedule never
	 * emails.
	 */
	public function failed(?Backup $backup, string $reason): void
	{
		if ($this->config->schedule->notice !== BackupNotice::Never) {
			$this->send(['stage' => 'failed', 'backup' => $backup?->name, 'reason' => $reason]);
		}
	}

	/**
	 * Sends the test email, to an address or the schedule's recipients,
	 * and returns whether it was queued (the site can send mail).
	 */
	public function test(?string $to = null): bool
	{
		return $this->send(['stage' => 'test'], $to);
	}

	/**
	 * Returns who the schedule's email goes to: an address, or a
	 * capability's accounts.
	 */
	public function recipient(?string $to = null): Recipient
	{
		$address = $to ?? ($this->config->schedule->to !== '' ? $this->config->schedule->to : $this->app->email);

		return $address !== '' ? Recipient::address($address) : Recipient::capability(Capability::SiteBackups);
	}

	/**
	 * Returns whether the site can send mail.
	 */
	public function canMail(): bool
	{
		return $this->notifier->isOn();
	}

	/**
	 * Returns an email as it would be sent, for the preview: who it's
	 * from and to, its subject, and its text. `stage` is `done` or
	 * `failed`, written about the newest backup (or none).
	 *
	 * @return array{from: string, to: string, subject: string, text: string}|null
	 */
	public function preview(string $stage, ?string $to = null): ?array
	{
		$newest  = $this->backups->all()[0] ?? null;
		$address = $this->recipient($to)->value;
		$content = $this->notifications->make(NotificationType::Backup->value)->mail(new Notifiable(new Address(str_contains($address, '@') ? $address : 'owner@example.com')), match ($stage) {
			'failed' => ['stage' => 'failed', 'backup' => $newest?->name, 'reason' => 'storage/backups/site/ isn\'t writable.'],
			default  => ['stage' => 'done', 'backup' => $newest?->name]
		});

		if ($content === null) {
			return null;
		}

		try {
			$from = $this->mail->sender($this->app);
			$from = $from->name === '' ? $from->email : "{$from->name} <{$from->email}>";
		} catch (Throwable) {
			$from = '';
		}

		return [
			'from'    => $from,
			'to'      => $address,
			'subject' => (string) $content->subject,
			'text'    => $this->layout->text($content, new Notifiable(new Address($address)))
		];
	}

	/**
	 * Queues the email, and returns whether it was.
	 *
	 * @param array<string, mixed> $data
	 */
	private function send(array $data, ?string $to = null): bool
	{
		try {
			return $this->notifier->send(NotificationType::Backup->value, $this->recipient($to), $data) !== [];
		} catch (Throwable) {
			return false;
		}
	}

	/**
	 * Whether the schedule's backup before this one failed.
	 */
	private function previousFailed(Backup $backup): bool
	{
		$before = array_find($this->backups->all(), static fn (Backup $other): bool => $other->kind === BackupKind::Scheduled && $other->name !== $backup->name && $other->created <= $backup->created && $other->status !== BackupStatus::Running);

		return $before?->status === BackupStatus::Failed;
	}
}
