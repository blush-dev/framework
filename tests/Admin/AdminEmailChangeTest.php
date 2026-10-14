<?php

/**
 * Admin email change tests.
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
use Psr\Http\Message\ResponseInterface;
use Blush\Admin\Http\EmailChangeController;
use Blush\Auth\Accounts;
use Blush\Auth\EmailChange;
use Blush\Auth\EmailChanges;
use Blush\Job\JobQueue;
use Blush\Job\JobRunner;
use Blush\Job\JobStatus;
use Blush\Mail\Mailer;
use Blush\Mail\Message;
use Blush\Mail\Testing\RecordingMailer;
use Blush\Notification\Notifications\EmailChangeNotification;
use Blush\Settings\Setting;
use Blush\Settings\SettingsStore;

#[CoversClass(EmailChange::class)]
#[CoversClass(EmailChangeController::class)]
#[CoversClass(EmailChangeNotification::class)]
#[CoversClass(EmailChanges::class)]
final class AdminEmailChangeTest extends TestCase
{
	use BootsAdmin;

	private RecordingMailer $mailer;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	/**
	 * Boots the admin, with a mail transport unless `$mail` is false, and
	 * a recording mailer.
	 *
	 * @param list<string> $roles
	 */
	private function site(bool $mail = true, array $roles = ['administrator']): void
	{
		$this->boot(roles: $roles, environment: ['APP_EMAIL' => 'site@example.test', 'MAIL_DSN' => $mail ? 'log' : '']);

		$this->mailer = new RecordingMailer();
		$this->app->container()->instance(Mailer::class, $this->mailer);
		$this->login();
	}

	private function accounts(): Accounts
	{
		return $this->app->container()->make(Accounts::class);
	}

	/**
	 * Runs every queued job to the end.
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

	/**
	 * Sends a request with the CSRF token.
	 *
	 * @param array<string, mixed> $data
	 */
	private function write(string $method, string $path, array $data = []): ResponseInterface
	{
		$token = self::json($this->send('GET', '/session'))['csrfToken'] ?? '';

		return $this->send($method, $path, $data === [] ? '' : (json_encode($data) ?: ''), ['X-CSRF-Token' => is_string($token) ? $token : '']);
	}

	/**
	 * Returns the `for` and token of the confirmation link in an email.
	 *
	 * @return array{for: string, token: string}
	 */
	private static function link(Message $message): array
	{
		self::assertSame(1, preg_match('#/admin/confirm-email\#for=([a-z0-9-]+)&token=([0-9a-f]+)#', $message->text, $match), 'The email links to the confirmation screen.');

		return ['for' => $match[1] ?? '', 'token' => $match[2] ?? ''];
	}

	/**
	 * Returns the new address an answer says waits on its link, or `null`.
	 *
	 * @param array<array-key, mixed> $data
	 */
	private static function waiting(array $data): ?string
	{
		$change = $data['emailChange'] ?? null;

		return is_array($change) && is_string($change['email'] ?? null) ? $change['email'] : null;
	}

	/**
	 * Confirms a change with a link's parts.
	 *
	 * @param array{for: string, token: string} $link
	 */
	private function confirm(array $link): ResponseInterface
	{
		return $this->write('POST', '/confirm-email', $link);
	}

	public function testYourOwnNewAddressWaitsOnItsLink(): void
	{
		$this->site();

		$answer = $this->write('PATCH', '/profile', ['email' => 'jane@new.example.test']);

		$this->assertSame(200, $answer->getStatusCode());
		$this->assertSame('jane@example.test', self::json($answer)['email'] ?? null, 'Nothing changes yet.');
		$session = self::json($this->send('GET', '/session'))['account'] ?? null;
		$this->assertSame('jane@new.example.test', self::waiting(self::json($answer)));
		$this->assertSame('jane@new.example.test', is_array($session) ? self::waiting($session) : null);

		$this->runAll();

		$confirm = $this->mailer->sentTo('jane@new.example.test');
		$this->assertCount(1, $confirm);
		$this->assertStringContainsString('Confirm your new email address', $confirm[0]->subject);

		$asked = $this->mailer->sentTo('jane@example.test');
		$this->assertCount(1, $asked, 'The old address is told a change was asked for.');
		$this->assertStringContainsString('jane@new.example.test', $asked[0]->text);
		$this->assertStringNotContainsString('confirm-email', $asked[0]->text, 'It has no link.');

		$link = self::link($confirm[0]);
		$this->assertSame($this->janeId(), $link['for']);

		$done = $this->confirm($link);

		$this->assertSame(200, $done->getStatusCode());
		$this->assertSame(['for' => 'account', 'email' => 'jane@new.example.test'], self::json($done));
		$this->assertSame('jane@new.example.test', $this->accounts()->find('jane')->email ?? null);

		$this->mailer->clear();
		$this->runAll();

		$changed = $this->mailer->sentTo('jane@example.test');
		$this->assertCount(1, $changed, 'The old address is told it changed.');
		$this->assertStringContainsString('changed', $changed[0]->subject);

		$this->assertSame(410, $this->confirm($link)->getStatusCode(), 'A link works once.');
	}

	public function testWithoutMailYourAddressChangesAtOnce(): void
	{
		$this->site(mail: false);

		$answer = $this->write('PATCH', '/profile', ['email' => 'jane@new.example.test']);

		$this->assertSame('jane@new.example.test', self::json($answer)['email'] ?? null);
		$this->assertNull(self::json($answer)['emailChange'] ?? null);
	}

	public function testAskingAgainReplacesTheLinkAndCancellingEndsIt(): void
	{
		$this->site();
		$this->write('PATCH', '/profile', ['email' => 'jane@first.example.test']);
		$this->runAll();
		$first = self::link($this->mailer->sentTo('jane@first.example.test')[0] ?? $this->fail());

		$this->write('PATCH', '/profile', ['email' => 'jane@second.example.test']);
		$this->runAll();
		$second = self::link($this->mailer->sentTo('jane@second.example.test')[0] ?? $this->fail());

		$this->assertSame(410, $this->confirm($first)->getStatusCode(), 'The first ask was replaced.');
		$this->assertSame(204, $this->write('DELETE', '/email-change/account')->getStatusCode());
		$this->assertSame(410, $this->confirm($second)->getStatusCode(), 'A cancelled change can\'t be confirmed.');
		$this->assertSame('jane@example.test', $this->accounts()->find('jane')->email ?? null);
		$this->assertSame(404, $this->write('DELETE', '/email-change/sam')->getStatusCode());
	}

	public function testAnAddressTakenInTheMeantimeIsRefused(): void
	{
		$this->site();
		$this->write('PATCH', '/profile', ['email' => 'shared@example.test']);
		$this->runAll();
		$link = self::link($this->mailer->sentTo('shared@example.test')[0] ?? $this->fail());

		$this->accounts()->create('sam', 'a long enough password', ['author'], email: 'shared@example.test');

		$this->assertSame(422, $this->confirm($link)->getStatusCode());
		$this->assertSame(410, $this->confirm(['for' => $link['for'], 'token' => str_repeat('0', 64)])->getStatusCode(), 'A wrong token is a dead link.');
	}

	public function testAnAdministratorChangesAnotherAddressAtOnceAndTellsTheOldOne(): void
	{
		$this->site();
		$this->accounts()->create('sam', 'a long enough password', ['author'], email: 'sam@example.test');

		$answer = $this->write('PATCH', '/accounts/sam', ['email' => 'sam@new.example.test']);

		$this->assertSame(200, $answer->getStatusCode());
		$this->assertSame('sam@new.example.test', self::account($answer)['email'] ?? null);

		$this->runAll();

		$told = $this->mailer->sentTo('sam@example.test');
		$this->assertCount(1, $told);
		$this->assertStringContainsString('an administrator of', $told[0]->text);
		$this->assertSame([], $this->mailer->sentTo('sam@new.example.test'), 'The new address needn\'t confirm.');
	}

	public function testTheSitesNewAddressWaitsOnItsLink(): void
	{
		$this->site();

		$answer = $this->write('PATCH', '/settings', ['set' => ['app.email' => 'owner@new.example.test', 'app.name' => 'Renamed']]);

		$this->assertSame(200, $answer->getStatusCode());
		$this->assertSame('owner@new.example.test', self::waiting(self::json($answer)));

		$saved = $this->app->container()->make(SettingsStore::class)->read();
		$this->assertFalse($saved->has(Setting::Email), 'The address isn\'t saved yet.');
		$this->assertSame('Renamed', $saved->get(Setting::Name), 'The other settings are.');
		$this->assertStringContainsString('"emailChange":{"email":"owner@new.example.test"', (string) $this->send('GET', '/settings/general')->getBody());

		$this->runAll();

		$confirm = $this->mailer->sentTo('owner@new.example.test');
		$this->assertCount(1, $confirm);
		$this->assertCount(1, $this->mailer->sentTo('site@example.test'), 'The old address is told.');

		$link = self::link($confirm[0]);
		$this->assertSame('site', $link['for']);
		$this->assertSame(['for' => 'site', 'email' => 'owner@new.example.test'], self::json($this->confirm($link)));
		$this->assertSame('owner@new.example.test', $this->app->container()->make(SettingsStore::class)->read()->get(Setting::Email));
	}

	public function testTheSitesAddressIsCheckedAndCanBeCancelled(): void
	{
		$this->site();

		$this->assertSame(422, $this->write('PATCH', '/settings', ['set' => ['app.email' => 'nope']])->getStatusCode());
		$this->runAll();
		$this->assertSame([], $this->mailer->sent(), 'Nothing is sent for an address that isn\'t one.');

		$this->write('PATCH', '/settings', ['set' => ['app.email' => 'owner@new.example.test']]);
		$this->assertSame(204, $this->write('DELETE', '/email-change/site')->getStatusCode());
		$this->assertStringContainsString('"emailChange":null', (string) $this->send('GET', '/settings/general')->getBody());
	}

	public function testWithoutMailTheSitesAddressSavesAtOnce(): void
	{
		$this->site(mail: false);

		$answer = $this->write('PATCH', '/settings', ['set' => ['app.email' => 'owner@new.example.test']]);

		$this->assertNull(self::json($answer)['emailChange'] ?? null);
		$this->assertSame('owner@new.example.test', $this->app->container()->make(SettingsStore::class)->read()->get(Setting::Email));
	}

	public function testCancellingTheSitesChangeNeedsSettings(): void
	{
		$this->site(roles: ['editor']);

		$this->assertSame(403, $this->write('DELETE', '/email-change/site')->getStatusCode());
	}
}
