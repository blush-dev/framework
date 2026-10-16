<?php

/**
 * Admin backup schedule test.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Admin\Http\BackupsController;
use Blush\Backup\Backup;
use Blush\Backup\BackupConfig;
use Blush\Backup\BackupFrequency;
use Blush\Backup\BackupKind;
use Blush\Backup\BackupMaker;
use Blush\Backup\BackupNotice;
use Blush\Backup\BackupNotices;
use Blush\Backup\BackupNotification;
use Blush\Backup\Backups;
use Blush\Backup\BackupSchedule;
use Blush\Backup\BackupStatus;
use Blush\Backup\ScheduledBackupJob;
use Blush\Job\JobQueue;
use Blush\Job\JobRecord;
use Blush\Job\JobStatus;
use Blush\Job\JobType;
use Blush\Job\Schedule;
use Blush\Setup\CheckStatus;
use Blush\Setup\SiteChecks;

/**
 * Scheduled backups (D-887): the schedule saved as a setting and put on
 * the scheduler, its job making a backup and keeping only its newest,
 * and the email it sends, which never carries a backup.
 */
#[CoversClass(BackupSchedule::class)]
#[CoversClass(ScheduledBackupJob::class)]
#[CoversClass(BackupNotices::class)]
#[CoversClass(BackupNotification::class)]
#[CoversClass(Backups::class)]
#[CoversClass(BackupsController::class)]
#[CoversClass(SiteChecks::class)]
final class AdminBackupScheduleTest extends TestCase
{
	use BootsAdmin;

	/**
	 * Boots the site as its owner, with mail it can send, and returns the
	 * CSRF token.
	 */
	private function owner(): string
	{
		$this->boot(roles: ['owner'], environment: ['APP_EMAIL' => 'site@example.test', 'MAIL_DSN' => 'log']);

		$token = self::json($this->login())['csrfToken'] ?? null;
		$this->assertIsString($token);

		return $token;
	}

	/**
	 * Writes the schedule into `config/backups.php` and boots the site
	 * again with it.
	 */
	private function scheduled(string $schedule): void
	{
		$this->writeTemporaryFile('config/backups.php', "<?php\nreturn new Blush\\Backup\\BackupConfig(schedule: {$schedule});\n");
		$this->boot(roles: ['owner'], environment: ['APP_EMAIL' => 'site@example.test', 'MAIL_DSN' => 'log']);
	}

	/**
	 * Runs the scheduled backup's job to its end.
	 */
	private function runScheduled(): JobStatus
	{
		$container = $this->app->container();
		$record    = new JobRecord('0199b6e2-0000-7000-8000-000000000001', JobType::ScheduledBackup->value);
		$data      = [];

		for ($runs = 0; $runs < 30; $runs++) {
			$result = $container->make(ScheduledBackupJob::class)->handle($record->with(['data' => $data]));

			if ($result->status !== JobStatus::Queued) {
				return $result->status;
			}

			$data = $result->data;
		}

		return JobStatus::Queued;
	}

	/**
	 * Returns the stages of the backup emails queued.
	 *
	 * @return list<mixed>
	 */
	private function emails(): array
	{
		$stages = [];

		foreach ($this->app->container()->make(JobQueue::class)->all() as $job) {
			if ($job->job === JobType::Notify->value && ($job->data['notification'] ?? null) === 'core/backup') {
				$data     = $job->data['data'] ?? [];
				$stages[] = is_array($data) ? ($data['stage'] ?? null) : null;
			}
		}

		return $stages;
	}

	public function testSavesTheScheduleAndPutsItOnTheScheduler(): void
	{
		$token = $this->owner();

		$listed = self::json($this->send('GET', '/backups'))['schedule'] ?? null;

		$this->assertIsArray($listed);
		$this->assertSame('off', $listed['frequency'] ?? null);
		$this->assertSame('failures', $listed['notice'] ?? null, 'Only when one fails, by default.');
		$this->assertArrayHasKey('next', $listed);
		$this->assertNull($listed['next']);
		$this->assertSame('site@example.test', $listed['siteEmail'] ?? null);
		$this->assertTrue($listed['canMail'] ?? null);

		$bad = $this->send('PUT', '/backups/schedule', '{"frequency": "daily", "hour": 3, "keep": 0}', ['X-CSRF-Token' => $token]);

		$this->assertSame(422, $bad->getStatusCode());

		$saved = self::json($this->send('PUT', '/backups/schedule', '{"frequency": "weekly", "day": 0, "hour": 3, "keep": 7, "parts": ["content", "settings"], "notice": "always", "to": ""}', ['X-CSRF-Token' => $token]))['schedule'] ?? null;

		$this->assertIsArray($saved);
		$this->assertSame('weekly', $saved['frequency'] ?? null);
		$this->assertIsString($saved['next'] ?? null);
		$this->assertStringContainsString('T03:00:00', (string) $saved['next']);

		$next = $this->scratchApplication(['APP_ENV' => 'development', 'APP_URL' => 'https://example.test', 'APP_SECRET' => str_repeat('s', 64)]);
		$next->boot();

		$this->assertNotNull($next->container()->make(Schedule::class)->get(JobType::ScheduledBackup->value), 'On the scheduler from the next request.');
		$this->assertSame(['content', 'settings'], $next->container()->make(BackupConfig::class)->schedule->parts);
	}

	public function testIsOffTheSchedulerWhenOff(): void
	{
		$this->owner();

		$this->assertNull($this->app->container()->make(Schedule::class)->get(JobType::ScheduledBackup->value));
	}

	public function testMakesTheScheduledBackupAndKeepsItsNewest(): void
	{
		$this->scheduled('new Blush\\Backup\\BackupSchedule(frequency: Blush\\Backup\\BackupFrequency::Daily, keep: 2, parts: ["redirects", "settings"], notice: Blush\\Backup\\BackupNotice::Always)');

		$container = $this->app->container();
		$backups   = $container->make(Backups::class);
		$manual    = $container->make(BackupMaker::class)->make(['roles']);

		for ($made = 0; $made < 3; $made++) {
			$this->assertSame(JobStatus::Done, $this->runScheduled());

			// Each a minute apart, as the schedule's would be days apart.
			foreach ($backups->all() as $backup) {
				if ($backup->kind === BackupKind::Scheduled && $backup->created >= time() - 60) {
					$backups->write($backup->with(['created' => $backup->created - 60 * (3 - $made)]));
				}
			}
		}

		$scheduled = array_values(array_filter($backups->all(), static fn (Backup $backup): bool => $backup->kind === BackupKind::Scheduled));

		$this->assertCount(2, $scheduled, 'The schedule keeps its two newest.');
		$this->assertSame(['settings', 'redirects'], $scheduled[0]->partNames(), 'In the order parts are listed.');
		$this->assertNotNull($backups->find($manual->name), 'One made by hand stays.');
		$this->assertSame(['done', 'done', 'done'], $this->emails(), 'An email after every backup, as the schedule asks.');
	}

	public function testEmailsOnlyWhenOneFailsAndWhenTheyWorkAgain(): void
	{
		$this->scheduled('new Blush\\Backup\\BackupSchedule(frequency: Blush\\Backup\\BackupFrequency::Daily)');

		$this->assertSame(JobStatus::Done, $this->runScheduled());
		$this->assertSame([], $this->emails(), 'Quiet while backups work.');

		$notices = $this->app->container()->make(BackupNotices::class);
		$backups = $this->app->container()->make(Backups::class);
		$failed  = new Backup('blush-2000-01-01-0300', BackupStatus::Failed, BackupKind::Scheduled, created: time() + 60, reason: 'The folder isn\'t writable.');
		$backups->write($failed);
		$notices->failed($failed, 'The folder isn\'t writable.');

		$this->assertSame(['failed'], $this->emails());

		$worked = new Backup('blush-2000-01-02-0300', BackupStatus::Done, BackupKind::Scheduled, created: time() + 120);
		$backups->write($worked);
		$notices->done($worked);

		$this->assertSame(['failed', 'recovered'], $this->emails(), 'One more when they work again.');
	}

	public function testSendsATestEmailAndPreviewsOne(): void
	{
		$token = $this->owner();

		$this->assertSame(422, $this->send('POST', '/backups/schedule/test', '{"to": "not an address"}', ['X-CSRF-Token' => $token])->getStatusCode());

		$sent = self::json($this->send('POST', '/backups/schedule/test', '{"to": "ops@example.test"}', ['X-CSRF-Token' => $token]));

		$this->assertSame(['sent' => true, 'to' => 'ops@example.test'], $sent);
		$this->assertSame(['test'], $this->emails());

		$email = self::json($this->send('POST', '/backups/schedule/preview', '{"stage": "failed"}', ['X-CSRF-Token' => $token]))['email'] ?? null;

		$this->assertIsArray($email);
		$this->assertSame('site@example.test', $email['to'] ?? null, 'The site\'s address when the schedule names none.');
		$subject = $email['subject'] ?? null;
		$text    = $email['text'] ?? null;

		$this->assertIsString($subject);
		$this->assertIsString($text);
		$this->assertStringStartsWith('Backup failed:', $subject);
		$this->assertStringContainsString('/admin/backups', $text);
		$this->assertStringNotContainsString('/download', $text, 'Never a link to a download.');
	}

	public function testTheScheduleChecksItsValues(): void
	{
		$schedule = BackupSchedule::fromArray(['frequency' => 'weekly', 'day' => 6, 'hour' => 23, 'keep' => 30, 'notice' => 'never']);

		$this->assertSame(BackupFrequency::Weekly, $schedule->frequency);
		$this->assertSame(BackupNotice::Never, $schedule->notice);
		$this->assertNotNull($schedule->when());
		$this->assertNull(new BackupSchedule()->when(), 'Off by default.');

		$this->expectException(\Blush\Config\InvalidConfig::class);
		BackupSchedule::fromArray(['hour' => 24]);
	}

	public function testSiteHealthWarnsWhenScheduledBackupsCantEmail(): void
	{
		$this->writeTemporaryFile('config/backups.php', "<?php\nreturn new Blush\\Backup\\BackupConfig(schedule: new Blush\\Backup\\BackupSchedule(frequency: Blush\\Backup\\BackupFrequency::Daily));\n");
		$this->boot(roles: ['owner'], environment: ['APP_EMAIL' => 'site@example.test', 'MAIL_DSN' => '']);

		$check = $this->app->container()->make(SiteChecks::class)->backups()['backups'] ?? null;

		$this->assertNotNull($check);
		$this->assertSame(CheckStatus::Warning, $check->status, 'A failed backup would go unnoticed.');
	}

	public function testSiteHealthSaysNothingWhenBackupsDontEmail(): void
	{
		$this->owner();

		$this->assertSame([], $this->app->container()->make(SiteChecks::class)->backups(), 'Not scheduled.');
	}
}
