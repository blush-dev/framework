<?php

/**
 * Admin mail tests.
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
use Blush\Admin\Http\AccountEditController;
use Blush\Admin\Http\ForgotPasswordController;
use Blush\Admin\Http\SettingsController;
use Blush\Admin\Http\SettingsEditController;
use Blush\Auth\Accounts;
use Blush\Auth\PasswordResets;
use Blush\Job\JobQueue;
use Blush\Job\JobRecord;
use Blush\Job\JobStatus;
use Blush\Mail\MailConfig;
use Blush\Mail\Mailer;
use Blush\Mail\MailTransport;
use Blush\Mail\Testing\RecordingMailer;

#[CoversClass(AccountEditController::class)]
#[CoversClass(ForgotPasswordController::class)]
#[CoversClass(PasswordResets::class)]
#[CoversClass(SettingsController::class)]
#[CoversClass(SettingsEditController::class)]
final class AdminMailTest extends TestCase
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
		$this->boot(roles: $roles, environment: ['APP_EMAIL' => 'site@example.test', ...($mail ? ['MAIL_DSN' => 'smtp://jane:s3cret@mail.example.test:587'] : ['MAIL_DSN' => ''])]);

		$this->mailer = new RecordingMailer();
		$this->app->container()->instance(Mailer::class, $this->mailer);
	}

	/**
	 * @return list<JobRecord>
	 */
	private function queued(): array
	{
		return $this->app->container()->make(JobQueue::class)->all(JobStatus::Queued);
	}

	/**
	 * Returns why a queued password link was asked for.
	 */
	private static function reason(JobRecord $job): ?string
	{
		$data = $job->data['data'] ?? null;

		return is_array($data) && is_string($data['reason'] ?? null) ? $data['reason'] : null;
	}

	private function forgot(string $login, string $ip = '203.0.113.5'): ResponseInterface
	{
		return $this->send('POST', '/forgot-password', json_encode(['login' => $login]) ?: '', ip: $ip);
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

	public function testForgotPasswordAnswersTheSameWhetherOrNotTheAccountExists(): void
	{
		$this->site();

		$known   = $this->forgot('jane');
		$unknown = $this->forgot('nobody@example.test', '203.0.113.6');

		$this->assertSame(202, $known->getStatusCode());
		$this->assertSame(202, $unknown->getStatusCode());
		$this->assertSame((string) $known->getBody(), (string) $unknown->getBody());

		$jobs = $this->queued();
		$this->assertCount(1, $jobs, 'Only the account that exists gets a link.');
		$this->assertSame('forgot', self::reason($jobs[0]));
	}

	public function testForgotPasswordFindsAnAccountByEmail(): void
	{
		$this->site();
		$this->forgot('JANE@example.test');

		$this->assertCount(1, $this->queued());
	}

	public function testForgotPasswordSendsAnAccountOneLinkEveryFewMinutes(): void
	{
		$this->site();
		$this->forgot('jane');
		$this->app->container()->make(JobQueue::class)->delete($this->queued()[0]);
		$this->forgot('jane', '203.0.113.9');

		$this->assertSame([], $this->queued(), 'A second ask within five minutes, from anywhere, is skipped.');
	}

	public function testForgotPasswordThrottlesAnAddress(): void
	{
		$this->site();

		for ($i = 0; $i < 5; $i++) {
			$this->assertSame(202, $this->forgot("nobody{$i}")->getStatusCode());
		}

		$this->assertSame(429, $this->forgot('jane')->getStatusCode());
		$this->assertSame(202, $this->forgot('jane', '203.0.113.7')->getStatusCode(), 'Another address may still ask.');
	}

	public function testForgotPasswordSkipsSuspendedAccountsAndChecksItsInput(): void
	{
		$this->site();
		$accounts = $this->app->container()->make(Accounts::class);
		$accounts->setSuspended($accounts->find('jane') ?? $this->fail(), true);

		$this->assertSame(202, $this->forgot('jane')->getStatusCode());
		$this->assertSame([], $this->queued());
		$this->assertSame(422, $this->forgot('  ')->getStatusCode());
	}

	public function testForgotPasswordIsntThereWithoutMail(): void
	{
		$this->site(mail: false);

		$this->assertSame(404, $this->forgot('jane')->getStatusCode());
		$this->assertStringContainsString('"mail":false', (string) $this->visit('GET', '/admin/')->getBody());
	}

	public function testTheShellSaysTheSiteCanSendMail(): void
	{
		$this->site();

		$this->assertStringContainsString('"mail":true', (string) $this->visit('GET', '/admin/')->getBody());
	}

	public function testANewAccountsLinkCanBeEmailed(): void
	{
		$this->site();
		$this->login();

		$created = $this->write('POST', '/accounts', ['username' => 'sam', 'email' => 'sam@example.test', 'roles' => ['author'], 'send' => true]);

		$this->assertSame(201, $created->getStatusCode());
		$this->assertArrayHasKey('link', self::json($created));
		$this->assertNull(self::json($created)['link']);
		$this->assertSame('sam@example.test', self::json($created)['sent'] ?? null);
		$this->assertSame('invited', self::account($created)['status'] ?? null);

		$jobs = $this->queued();
		$this->assertCount(1, $jobs);
		$this->assertSame('invite', self::reason($jobs[0]));
		$this->assertSame($this->janeId(), $jobs[0]->account, 'It says who sent it.');
	}

	public function testAnAccountsLinkCanBeEmailed(): void
	{
		$this->site();
		$this->login();
		$this->write('POST', '/accounts', ['username' => 'sam', 'email' => 'sam@example.test', 'roles' => ['author']]);

		$answer = $this->write('POST', '/accounts/sam/link', ['send' => true]);

		$this->assertSame(200, $answer->getStatusCode());
		$this->assertSame('sam@example.test', self::json($answer)['sent'] ?? null);
		$this->assertArrayNotHasKey('link', self::json($answer));
		$this->assertSame('invite', self::reason($this->queued()[0]), 'Sam hasn\'t signed in yet.');
	}

	public function testEmailingNeedsMail(): void
	{
		$this->site(mail: false);
		$this->login();

		$this->assertSame(422, $this->write('POST', '/accounts', ['username' => 'sam', 'email' => 'sam@example.test', 'roles' => ['author'], 'send' => true])->getStatusCode());
		$this->assertNull($this->app->container()->make(Accounts::class)->find('sam'), 'Nothing is made.');
		$this->assertSame(201, $this->write('POST', '/accounts', ['username' => 'sam', 'email' => 'sam@example.test', 'roles' => ['author']])->getStatusCode());
		$this->assertSame(422, $this->write('POST', '/accounts/sam/link', ['send' => true])->getStatusCode());
	}

	public function testTheEmailScreenShowsTheTransportWithoutItsPassword(): void
	{
		$this->site();
		$this->login();

		$screen = $this->send('GET', '/settings/email');
		$body   = (string) $screen->getBody();

		$this->assertSame(200, $screen->getStatusCode());
		$this->assertStringContainsString('smtp://jane@mail.example.test:587', $body);
		$this->assertStringNotContainsString('s3cret', $body);
		$this->assertStringContainsString('"setting":"mail.fromAddress"', $body);
		$this->assertStringContainsString('"kind":"test"', $body);
	}

	public function testTheEmailScreenSaysWhenMailFallsBackToPhps(): void
	{
		$this->boot(environment: ['APP_EMAIL' => 'site@example.test', 'APP_ENV' => 'production', 'MAIL_DSN' => null], roles: ['administrator']);
		$this->app->container()->instance(MailTransport::class, new MailTransport(new MailConfig(), true));
		$this->login();

		$body = (string) $this->send('GET', '/settings/email')->getBody();

		$this->assertStringContainsString('PHP\'s own mail (no setup)', $body);
		$this->assertStringContainsString('Password links may land in spam', $body, 'A live site is warned.');
		$this->assertStringContainsString('"kind":"test"', $body);
	}

	public function testSavesTheSender(): void
	{
		$this->site();
		$this->login();

		$this->assertSame(422, $this->write('PATCH', '/settings', ['set' => ['mail.fromAddress' => 'nope']])->getStatusCode());
		$this->assertSame(200, $this->write('PATCH', '/settings', ['set' => ['mail.fromAddress' => 'news@example.test', 'mail.fromName' => 'The News']])->getStatusCode());
	}

	public function testSendsATestToTheSignedInAccount(): void
	{
		$this->site();
		$this->login();

		$answer = $this->write('POST', '/settings/email/test');

		$this->assertSame(200, $answer->getStatusCode());
		$this->assertCount(1, $this->mailer->sentTo('jane@example.test'));

		$this->mailer->failWith('Connection refused');
		$failed = $this->write('POST', '/settings/email/test');

		$this->assertSame(422, $failed->getStatusCode());
		$this->assertStringContainsString('Connection refused', (string) $failed->getBody());
	}

	public function testATestNeedsSettingsAndMail(): void
	{
		$this->site(roles: ['editor']);
		$this->login();

		$this->assertSame(403, $this->write('POST', '/settings/email/test')->getStatusCode());
	}
}
