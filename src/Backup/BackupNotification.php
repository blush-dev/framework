<?php

/**
 * Backup notification.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Backup;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;
use Blush\Admin\AdminConfig;
use Blush\Clock\DateFormat;
use Blush\Core\AppConfig;
use Blush\Notification\MailContent;
use Blush\Notification\Notifiable;
use Blush\Notification\Notification;
use Blush\Translation\CurrentLocale;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Emails about scheduled backups (`core/backup`, D-887), by `stage`:
 * `done` (after every backup, when the schedule asks for it), `failed`
 * (with its `reason`), `recovered` (the first that worked after one
 * failed), and `test` (Send a Test Email). `backup` names the backup.
 *
 * An email says what happened, when, and what the backup holds, and
 * links to Tools → Backups, which needs a sign-in. It never attaches a
 * backup or links to a download: a backup holds password hashes, and a
 * link in an inbox works for whoever holds the inbox.
 */
final class BackupNotification extends Notification
{
	public function __construct(
		private readonly Backups $backups,
		private readonly BackupParts $parts,
		private readonly BackupConfig $config,
		private readonly AdminConfig $admin,
		private readonly AppConfig $app,
		private readonly CurrentLocale $locale,
		private readonly ClockInterface $clock,
		private readonly Messages $messages
	) {}

	#[Override]
	public function label(): Text
	{
		return $this->messages->t('backup.notification.label', 'Scheduled backups');
	}

	#[Override]
	public function mail(Notifiable $to, array $data): ?MailContent
	{
		$stage  = $data['stage'] ?? null;
		$backup = is_string($data['backup'] ?? null) ? $this->backups->find($data['backup']) : null;
		$reason = is_string($data['reason'] ?? null) ? $data['reason'] : '';

		return match ($stage) {
			'test'      => $this->test(),
			'done'      => $backup === null ? null : $this->done($backup),
			'recovered' => $backup === null ? null : $this->recovered($backup),
			'failed'    => $this->failed($backup, $reason),
			default     => null
		};
	}

	/**
	 * The email after a backup that worked.
	 */
	private function done(Backup $backup): MailContent
	{
		$site = $this->app->name;

		return new MailContent($this->messages->t('backup.notification.done.subject', 'Backed up: {site}, {when}', site: $site, when: $this->when($backup->created)))
			->line($this->messages->t('backup.notification.done.body', 'The scheduled backup of {site} finished {when}.', site: $site, when: $this->when($backup->created)))
			->line($this->messages->t('backup.notification.holds', 'Size: {size}. Holds: {parts}.', size: $this->size($backup->size), parts: $this->holds($backup)))
			->line($this->kept())
			->action($this->messages->t('backup.notification.action', 'See Your Backups'), $this->url())
			->note($this->messages->t('backup.notification.done.note', 'The backup stays on the server: backups are never sent by email. You get this because the schedule emails after every backup.'));
	}

	/**
	 * The email after the first backup that worked once one had failed.
	 */
	private function recovered(Backup $backup): MailContent
	{
		$site = $this->app->name;

		return new MailContent($this->messages->t('backup.notification.recovered.subject', 'Backups work again: {site}', site: $site))
			->line($this->messages->t('backup.notification.recovered.body', 'The scheduled backup of {site} worked {when}, after the last one failed.', site: $site, when: $this->when($backup->created)))
			->line($this->messages->t('backup.notification.holds', 'Size: {size}. Holds: {parts}.', size: $this->size($backup->size), parts: $this->holds($backup)))
			->action($this->messages->t('backup.notification.action', 'See Your Backups'), $this->url())
			->note($this->messages->t('backup.notification.failures_note', 'You get an email each time a scheduled backup fails, and one when they work again.'));
	}

	/**
	 * The email after a backup that failed.
	 */
	private function failed(?Backup $backup, string $reason): MailContent
	{
		$site   = $this->app->name;
		$when   = $this->when($backup->created ?? $this->clock->now()->getTimestamp());
		$newest = array_find($this->backups->all(), static fn (Backup $kept): bool => $kept->status === BackupStatus::Done);
		$mail   = new MailContent($this->messages->t('backup.notification.failed.subject', 'Backup failed: {site}, {when}', site: $site, when: $when))
			->line($this->messages->t('backup.notification.failed.body', 'The scheduled backup of {site} failed {when}. Nothing was kept, and no older backup was removed.', site: $site, when: $when));

		if ($reason !== '') {
			$mail = $mail->line($this->messages->t('backup.notification.failed.reason', 'What happened: {reason}', reason: $reason));
		}

		return $mail
			->line($newest === null
				? $this->messages->t('backup.notification.failed.none', 'There\'s no good backup of the site yet.')
				: $this->messages->t('backup.notification.failed.newest', 'The newest good backup is from {when}.', when: $this->when($newest->created)))
			->action($this->messages->t('backup.notification.failed.action', 'See What Happened'), $this->url())
			->note($this->config->schedule->notice === BackupNotice::Always
				? $this->messages->t('backup.notification.done.note', 'The backup stays on the server: backups are never sent by email. You get this because the schedule emails after every backup.')
				: $this->messages->t('backup.notification.failures_note', 'You get an email each time a scheduled backup fails, and one when they work again.'));
	}

	/**
	 * The test email.
	 */
	private function test(): MailContent
	{
		$site = $this->app->name;

		return new MailContent($this->messages->t('backup.notification.test.subject', 'A test email about the backups of {site}', site: $site))
			->line($this->messages->t('backup.notification.test.body', 'This is a test. When a scheduled backup of {site} fails, or after each one if the schedule asks for it, an email like this comes to this address.', site: $site))
			->action($this->messages->t('backup.notification.action', 'See Your Backups'), $this->url())
			->note($this->messages->t('backup.notification.test.note', 'Backups are never sent by email, and no email links to a download.'));
	}

	/**
	 * Says how many of the schedule's backups are kept.
	 */
	private function kept(): Text
	{
		$scheduled = array_filter($this->backups->all(), static fn (Backup $backup): bool => $backup->kind === BackupKind::Scheduled && $backup->status === BackupStatus::Done);

		return $this->messages->t('backup.notification.kept', 'Kept on the server: {count, plural, one {# scheduled backup} other {# scheduled backups}}.', count: count($scheduled));
	}

	/**
	 * Returns the parts a backup holds, by label.
	 */
	private function holds(Backup $backup): string
	{
		return count($backup->parts) === count($this->parts->all())
			? (string) $this->messages->t('backup.notification.everything', 'everything')
			: implode(', ', array_map(fn (string $name): string => (string) ($this->parts->get($name)->label ?? $name), $backup->partNames()));
	}

	/**
	 * Returns a time in the site's time zone and language.
	 */
	private function when(int $time): string
	{
		return DateFormat::format(new DateTimeImmutable("@{$time}"), $this->locale->get(), $this->app->timezone(), 'long', 'short');
	}

	/**
	 * Says a size in bytes as people read it.
	 */
	private function size(int $bytes): Text
	{
		return match (true) {
			$bytes >= 1024 ** 3 => $this->messages->t('backup.notification.gigabytes', '{size, number} GB', size: round($bytes / 1024 ** 3, 2)),
			$bytes >= 1024 ** 2 => $this->messages->t('backup.notification.megabytes', '{size, number} MB', size: round($bytes / 1024 ** 2, 1)),
			default             => $this->messages->t('backup.notification.kilobytes', '{size, number} KB', size: max(1, (int) round($bytes / 1024)))
		};
	}

	/**
	 * Returns the address of Tools → Backups.
	 */
	private function url(): string
	{
		return $this->app->absoluteUrl("{$this->admin->path}/tools?tab=backups");
	}
}
