<?php

/**
 * Notification tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Auth\Accounts;
use Blush\Job\JobQueue;
use Blush\Job\JobRunner;
use Blush\Job\JobStatus;
use Blush\Job\UrgentJobs;
use Blush\Mail\Mailer;
use Blush\Mail\Testing\RecordingMailer;
use Blush\Notification\MailContent;
use Blush\Notification\MailLayout;
use Blush\Notification\NotificationException;
use Blush\Notification\NotificationRegistry;
use Blush\Notification\Notifications\PasswordLinkNotification;
use Blush\Notification\Notifier;
use Blush\Notification\NotifyJob;
use Blush\Notification\Recipient;
use Blush\Notification\UnknownNotification;
use Blush\Tests\Admin\BootsAdmin;
use Blush\Tests\Fixtures\Notification\HelloNotification;

#[CoversClass(MailContent::class)]
#[CoversClass(MailLayout::class)]
#[CoversClass(Notifier::class)]
#[CoversClass(NotifyJob::class)]
#[CoversClass(PasswordLinkNotification::class)]
#[CoversClass(Recipient::class)]
#[CoversClass(UrgentJobs::class)]
final class NotificationTest extends TestCase
{
	use BootsAdmin;

	private RecordingMailer $mailer;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	/**
	 * Boots the admin with a mail transport (or none), the hello
	 * notification registered, and a recording mailer.
	 */
	private function site(bool $mail = true): void
	{
		$this->boot(environment: ['APP_EMAIL' => 'site@example.test', ...($mail ? ['MAIL_DSN' => 'log'] : ['MAIL_DSN' => ''])]);

		$this->mailer = new RecordingMailer();
		$this->app->container()->instance(Mailer::class, $this->mailer);
		$this->app->container()->make(NotificationRegistry::class)->register('test/hello', HelloNotification::class);
	}

	private function notifier(): Notifier
	{
		return $this->app->container()->make(Notifier::class);
	}

	private function accounts(): Accounts
	{
		return $this->app->container()->make(Accounts::class);
	}

	/**
	 * Runs every queued job, and what they queue, to the end.
	 */
	private function runAll(): void
	{
		$queue  = $this->app->container()->make(JobQueue::class);
		$runner = $this->app->container()->make(JobRunner::class);

		while (($queued = array_filter($queue->all(JobStatus::Queued), static fn ($job): bool => $job->attempts === 0)) !== []) {
			foreach ($queued as $job) {
				$runner->finish($job);
			}
		}
	}

	public function testEmailsAPasswordLinkThatSetsThePassword(): void
	{
		$this->site();
		$this->notifier()->send('core/password-link', Recipient::account($this->janeId()), ['reason' => 'forgot']);

		$this->assertSame([], $this->mailer->sent(), 'Nothing is sent in the request.');

		$this->runAll();

		$sent = $this->mailer->sentTo('jane@example.test');
		$this->assertCount(1, $sent);
		$this->assertStringContainsString('Reset your password', $sent[0]->subject);
		$this->assertStringContainsString('If you didn\'t ask', $sent[0]->text);
		$this->assertSame(1, preg_match('#https://example\.test/admin/set-password\#account=jane&token=([0-9a-f]+)#', $sent[0]->text, $match));
		$token = $match[1] ?? '';
		$this->assertStringContainsString('set-password#account=jane&amp;token=' . $token, $sent[0]->html, 'The HTML part links to it, escaped.');

		$link = $this->accounts()->find('jane')->passwordLink ?? null;
		$this->assertNotNull($link);
		$this->assertLessThanOrEqual(time() + 3600, $link->expires, 'A link someone asked for lasts an hour.');

		$this->assertSame(204, $this->send('POST', '/set-password', json_encode(['account' => 'jane', 'token' => $token, 'password' => 'a brand new long password']) ?: '')->getStatusCode());
	}

	public function testAPasswordLinkIsSentRightAfterTheRequest(): void
	{
		$this->site();
		$urgent = $this->app->container()->make(UrgentJobs::class);

		$this->notifier()->send('test/hello', Recipient::address('sam@example.test'));
		$this->assertTrue($urgent->isEmpty(), 'Most notifications wait for a runner.');

		$this->notifier()->send('core/password-link', Recipient::account($this->janeId()), ['reason' => 'reset']);
		$this->assertFalse($urgent->isEmpty());

		// What `WebRunner::afterResponse()` does once the response is sent.
		$urgent->run();

		$this->assertTrue($urgent->isEmpty());
		$this->assertCount(1, $this->mailer->sentTo('jane@example.test'));
		$this->assertSame([], $this->mailer->sentTo('sam@example.test'), 'The other is still queued.');
	}

	public function testAnInvitationLinkLastsAWeek(): void
	{
		$this->site();
		$this->notifier()->send('core/password-link', Recipient::account($this->janeId()), ['reason' => 'invite']);
		$this->runAll();

		$this->assertStringContainsString('Your account on', $this->mailer->sent()[0]->subject);
		$this->assertGreaterThan(time() + 86400 * 6, $this->accounts()->find('jane')->passwordLink->expires ?? 0);
	}

	public function testQueuesOneLinkAtATimeForAnAccount(): void
	{
		$this->site();

		$first  = $this->notifier()->send('core/password-link', Recipient::account($this->janeId()), ['reason' => 'forgot']);
		$second = $this->notifier()->send('core/password-link', Recipient::account($this->janeId()), ['reason' => 'reset']);

		$this->assertSame($first[0]->id, $second[0]->id);

		$this->runAll();
		$this->assertCount(1, $this->mailer->sent());
	}

	public function testSendsNothingWithoutATransport(): void
	{
		$this->site(mail: false);

		$this->assertFalse($this->notifier()->isOn());
		$this->assertSame([], $this->notifier()->send('test/hello', Recipient::address('sam@example.test')));
		$this->assertSame([], $this->app->container()->make(JobQueue::class)->all());
	}

	public function testRefusesUnknownNotificationsAndDataThatIsntPlain(): void
	{
		$this->site();

		try {
			$this->notifier()->send('test/nope', Recipient::address('sam@example.test'));
			$this->fail('Unknown.');
		} catch (UnknownNotification) {
			$this->addToAssertionCount(1);
		}

		$this->expectException(NotificationException::class);
		$this->notifier()->send('test/hello', Recipient::address('sam@example.test'), ['account' => $this->accounts()->find('jane')]);
	}

	public function testSkipsSuspendedMissingAndTokenOnlyAccounts(): void
	{
		$this->site();
		$this->accounts()->setSuspended($this->accounts()->find('jane') ?? $this->fail(), true);
		$robot = $this->accounts()->createTokenOnly('robot', ['editor'], email: 'robot@example.test');

		$jobs = $this->notifier()->send('core/password-link', [Recipient::account($this->janeId()), Recipient::account('0199b6e2-0000-7000-8000-000000000000'), Recipient::account($robot)]);
		$this->runAll();

		$this->assertSame([], $this->mailer->sent());

		foreach ($jobs as $job) {
			$this->assertSame(JobStatus::Done, $this->app->container()->make(JobQueue::class)->find($job->id)?->status);
		}
	}

	public function testACapabilityReachesEveryAccountWithItWhenItsSent(): void
	{
		$this->site();
		$this->accounts()->create('olive', self::PASSWORD, ['administrator'], email: 'olive@example.test');
		$this->accounts()->create('sam', self::PASSWORD, ['member'], email: 'sam@example.test');
		$this->notifier()->send('test/hello', Recipient::capability('accounts.edit'));

		// Made after it was queued, before it ran.
		$this->accounts()->create('pat', self::PASSWORD, ['administrator'], email: 'pat@example.test');
		$this->runAll();

		$this->assertCount(1, $this->mailer->sentTo('olive@example.test'));
		$this->assertCount(1, $this->mailer->sentTo('pat@example.test'));
		$this->assertSame([], $this->mailer->sentTo('sam@example.test'));
		$this->assertSame([], $this->mailer->sentTo('jane@example.test'), 'An editor can\'t edit accounts.');
	}

	public function testWritesInTheRecipientsLanguageAndEscapesTheHtml(): void
	{
		$this->site();
		$jane = $this->accounts()->find('jane') ?? $this->fail();
		$this->accounts()->setPreferences($jane, $jane->preferences->withLanguage('ja'));

		$this->notifier()->send('test/hello', [Recipient::account($jane), Recipient::address('sam@example.test')]);
		$this->runAll();

		$this->assertSame('Hello in ja', $this->mailer->sentTo('jane@example.test')[0]->subject);
		$this->assertSame('Hello in en_US', $this->mailer->sentTo('sam@example.test')[0]->subject, 'An address is written in the site\'s language.');

		$message = $this->mailer->sentTo('sam@example.test')[0];
		$this->assertStringContainsString('&lt;b&gt;bold&lt;/b&gt; &amp; more', $message->html);
		$this->assertStringContainsString('href="https://example.test/open?a=1&amp;b=2"', $message->html);
		$this->assertStringContainsString("Open:\nhttps://example.test/open?a=1&b=2", $message->text);
		$this->assertStringContainsString('<b>bold</b> & more', $message->text, 'Text is text.');
	}

	public function testNothingToSendIsDone(): void
	{
		$this->site();
		$jobs = $this->notifier()->send('test/hello', Recipient::address('sam@example.test'), ['skip' => true]);
		$this->runAll();

		$this->assertSame([], $this->mailer->sent());
		$this->assertSame(JobStatus::Done, $this->app->container()->make(JobQueue::class)->find($jobs[0]->id)?->status);
	}

	public function testATransportThatFailsIsTriedAgain(): void
	{
		$this->site();
		$this->mailer->failWith('Connection refused');

		$jobs = $this->notifier()->send('test/hello', Recipient::address('sam@example.test'));
		$this->runAll();

		$job = $this->app->container()->make(JobQueue::class)->find($jobs[0]->id);
		$this->assertSame(JobStatus::Queued, $job?->status, 'It waits to try again.');
		$this->assertSame(1, $job->attempts);
		$this->assertStringContainsString('Connection refused', (string) $job->error);
	}

	public function testRecipientsAreChecked(): void
	{
		$this->assertSame(['kind' => 'address', 'value' => 'a@example.test'], Recipient::address(' a@example.test ')->toArray());
		$this->assertSame('address:a@example.test', Recipient::address('A@Example.test')->key());
		$this->assertNull(Recipient::fromArray(['kind' => 'address', 'value' => 'nope']));
		$this->assertNull(Recipient::fromArray(['kind' => 'group', 'value' => 'x']));
		$this->assertEquals(Recipient::capability('site.jobs'), Recipient::fromArray(['kind' => 'capability', 'value' => 'site.jobs']));

		$this->expectException(NotificationException::class);
		Recipient::capability('nope');
	}
}
