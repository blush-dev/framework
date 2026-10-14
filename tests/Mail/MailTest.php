<?php

/**
 * Mail tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Mail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Config\InvalidConfig;
use Blush\Core\AppConfig;
use Blush\Mail\Address;
use Blush\Mail\Attachment;
use Blush\Mail\LogMailer;
use Blush\Mail\MailConfig;
use Blush\Mail\Mailer;
use Blush\Mail\MailException;
use Blush\Mail\MailNotSetUp;
use Blush\Mail\MailServiceProvider;
use Blush\Mail\MailTransport;
use Blush\Mail\Message;
use Blush\Mail\SymfonyMailer;
use Blush\Mail\Testing\RecordingMailer;
use Blush\Mail\UnconfiguredMailer;
use Blush\Setup\CheckStatus;
use Blush\Setup\SiteChecks;
use Blush\Tests\BootsScratchSite;
use Blush\Tests\Fixtures\Log\MemoryLogger;

#[CoversClass(Address::class)]
#[CoversClass(Attachment::class)]
#[CoversClass(LogMailer::class)]
#[CoversClass(MailConfig::class)]
#[CoversClass(MailServiceProvider::class)]
#[CoversClass(MailTransport::class)]
#[CoversClass(Message::class)]
#[CoversClass(RecordingMailer::class)]
#[CoversClass(SiteChecks::class)]
#[CoversClass(SymfonyMailer::class)]
#[CoversClass(UnconfiguredMailer::class)]
final class MailTest extends TestCase
{
	use BootsScratchSite;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	public function testReadsTheTransportAndItsShortForms(): void
	{
		$this->assertNull(new MailConfig()->dsn, 'Unset.');
		$this->assertSame('', new MailConfig('')->dsn, 'Empty is off.');
		$this->assertSame('log://default', new MailConfig('log')->dsn);
		$this->assertSame('null://null', new MailConfig('null')->dsn);
		$this->assertSame('native://default', new MailConfig('native')->dsn);
		$this->assertSame('sendmail://default', new MailConfig(' sendmail ')->dsn);
		$this->assertSame('smtp', new MailTransport(new MailConfig('smtp://jane:secret@mail.example.com:587'), false)->scheme());
	}

	public function testFallsBackToPhpsOwnMailWhenNothingIsSet(): void
	{
		$fallback = new MailTransport(new MailConfig(), true);

		$this->assertSame('native://default', $fallback->dsn());
		$this->assertTrue($fallback->isSetUp());
		$this->assertTrue($fallback->isFallback());
		$this->assertSame('native', $fallback->scheme());

		$none = new MailTransport(new MailConfig(), false);
		$this->assertNull($none->dsn(), 'Without a mail program, there\'s nothing to fall back to.');
		$this->assertFalse($none->isSetUp());

		$off = new MailTransport(new MailConfig(''), true);
		$this->assertNull($off->dsn(), 'Empty turns mail off, mail program or not.');
		$this->assertTrue($off->isOff());

		$set = new MailTransport(new MailConfig('smtp://mail.example.com:587'), true);
		$this->assertSame('smtp://mail.example.com:587', $set->dsn());
		$this->assertFalse($set->isFallback());
	}

	public function testDescribesTheTransportWithoutItsPassword(): void
	{
		$transport = new MailTransport(new MailConfig('smtp://jane%40example.com:s3cret@mail.example.com:587?verify_peer=0'), false);

		$this->assertSame('smtp://jane@example.com@mail.example.com:587', $transport->describe());
		$this->assertStringNotContainsString('s3cret', (string) $transport->describe());
		$this->assertSame('log://default', new MailTransport(new MailConfig('log'), false)->describe());
		$this->assertNull(new MailTransport(new MailConfig(), false)->describe());
	}

	public function testRefusesWhatIsntATransportOrAnAddress(): void
	{
		foreach ([['dsn' => 'mail.example.com'], ['fromAddress' => 'nope'], ['fromName' => "Two\nlines"]] as $data) {
			try {
				MailConfig::fromArray($data);
				$this->fail('Refused: ' . json_encode($data));
			} catch (InvalidConfig) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testSendsFromTheSavedSenderElseTheSites(): void
	{
		$app = new AppConfig(name: 'The Daily', email: 'site@example.com');

		$this->assertSame('The Daily <site@example.com>', (string) new MailConfig('log')->sender($app));
		$this->assertSame('News <news@example.com>', (string) new MailConfig('log', 'news@example.com', 'News')->sender($app));

		$this->expectException(MailException::class);
		new MailConfig('log')->sender(new AppConfig());
	}

	public function testChecksMessagesAndAddresses(): void
	{
		$cases = [
			static fn (): Address => new Address('not an address'),
			static fn (): Address => new Address('a@example.com', "Line\nbreak"),
			static fn (): Message => new Message([], 'Subject', 'Text'),
			static fn (): Message => new Message([new Address('a@example.com')], "Two\nlines", 'Text'),
			static fn (): Attachment => new Attachment('x', '../etc/passwd')
		];

		foreach ($cases as $case) {
			try {
				$case();
				$this->fail('Refused.');
			} catch (MailException) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testTheLogTransportWritesTheWholeMessage(): void
	{
		$logger = new MemoryLogger();
		$mailer = new LogMailer(new MailConfig('log'), new AppConfig(name: 'The Daily', email: 'site@example.com'), $logger);
		$mailer->send(new Message([new Address('jane@example.com', 'Jane')], 'Hello', 'Plain words', '<p>HTML words</p>'));

		$written = implode("\n", array_column($logger->records, 'message'));

		$this->assertSame('warning', $logger->records[0]['level'] ?? null, 'Written whatever the log keeps by default.');

		$this->assertStringContainsString('From: The Daily <site@example.com>', $written);
		$this->assertStringContainsString('To: Jane <jane@example.com>', $written);
		$this->assertStringContainsString('Plain words', $written);
		$this->assertStringContainsString('<p>HTML words</p>', $written);
	}

	public function testBindsTheMailerForTheTransport(): void
	{
		$cases = [
			[['MAIL_DSN' => ''], UnconfiguredMailer::class],
			[['MAIL_DSN' => 'native'], SymfonyMailer::class],
			[['MAIL_DSN' => 'log'], LogMailer::class],
			[['MAIL_DSN' => 'null'], SymfonyMailer::class]
		];

		foreach ($cases as [$environment, $class]) {
			$app = $this->scratchApplication(['APP_EMAIL' => 'site@example.com', ...$environment]);
			$app->boot();

			$this->assertInstanceOf($class, $app->container()->make(Mailer::class));
		}
	}

	public function testSiteHealthSaysHowMailIsSent(): void
	{
		$cases = [
			['production', new MailConfig(), true, CheckStatus::Warning, 'A live site on PHP\'s own mail.'],
			['development', new MailConfig(), true, CheckStatus::Pass, 'Fine for development.'],
			['production', new MailConfig(), false, CheckStatus::Warning, 'No way to send at all.'],
			['production', new MailConfig(''), true, CheckStatus::Pass, 'Turned off on purpose.'],
			['production', new MailConfig('log'), true, CheckStatus::Warning, 'Nothing is delivered.'],
			['production', new MailConfig('smtp://mail.example.com:587'), true, CheckStatus::Pass, 'A real transport.']
		];

		foreach ($cases as [$environment, $config, $native, $status, $why]) {
			$app = $this->scratchApplication(['APP_ENV' => $environment]);
			$app->boot();
			$app->container()->instance(MailTransport::class, new MailTransport($config, $native));

			$this->assertSame($status, $app->container()->make(SiteChecks::class)->mail()['mail']->status, $why);
		}
	}

	public function testSymfonySendsThroughTheTransportItNames(): void
	{
		$mailer = new SymfonyMailer(new MailTransport(new MailConfig('null'), false), new MailConfig('null'), new AppConfig(email: 'site@example.com'));
		$mailer->send(new Message([new Address('jane@example.com')], 'Hello', 'Text', '<p>Text</p>', attachments: [new Attachment('data', 'a.txt', 'text/plain')]));

		$this->addToAssertionCount(1);

		$this->expectException(MailException::class);
		new SymfonyMailer(new MailTransport(new MailConfig('nosuch://default'), false), new MailConfig('nosuch://default'), new AppConfig(email: 'site@example.com'))->send(new Message([new Address('jane@example.com')], 'Hello', 'Text'));
	}

	public function testAnUnconfiguredMailerRefuses(): void
	{
		$this->expectException(MailNotSetUp::class);
		new UnconfiguredMailer()->send(new Message([new Address('jane@example.com')], 'Hello', 'Text'));
	}

	public function testARecordingMailerKeepsWhatsSent(): void
	{
		$mailer = new RecordingMailer();
		$mailer->send(new Message([new Address('Jane@Example.com')], 'Hello', 'Text'));

		$this->assertCount(1, $mailer->sentTo('jane@example.com'));
		$this->assertSame([], $mailer->sentTo('sam@example.com'));

		$mailer->failWith('Down');

		$this->expectException(MailException::class);
		$mailer->send(new Message([new Address('jane@example.com')], 'Hello', 'Text'));
	}
}
