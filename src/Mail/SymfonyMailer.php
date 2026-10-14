<?php

/**
 * Symfony mailer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mail;

use Override;
use Throwable;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address as MimeAddress;
use Symfony\Component\Mime\Email;
use Blush\Core\AppConfig;

/**
 * Sends mail over `symfony/mailer` (D-832): SMTP, PHP's own mail
 * (`native`), sendmail, and `null`, whatever `MailTransport` says. The only class that names a
 * Symfony one; nothing outside it knows they're there, so the library
 * can be replaced without changing a caller.
 */
final class SymfonyMailer implements Mailer
{
	/**
	 * The transport, built when the first message is sent.
	 */
	private ?TransportInterface $transport = null;

	public function __construct(
		private readonly MailTransport $mail,
		private readonly MailConfig $config,
		private readonly AppConfig $app
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function send(Message $message): void
	{
		$from  = $message->from ?? $this->config->sender($this->app);
		$email = new Email()
			->from(self::address($from))
			->to(...array_map(self::address(...), $message->to))
			->subject($message->subject)
			->text($message->text);

		if ($message->html !== '') {
			$email->html($message->html);
		}

		if ($message->replyTo !== null) {
			$email->replyTo(self::address($message->replyTo));
		}

		foreach ($message->attachments as $attachment) {
			$email->attach($attachment->contents, $attachment->name, $attachment->type);
		}

		try {
			$this->transport()->send($email);
		} catch (Throwable $error) {
			throw new MailException(sprintf('The message "%s" wasn\'t sent: %s', $message->subject, $error->getMessage()), 0, $error);
		}
	}

	/**
	 * Returns the transport, building it the first time.
	 *
	 * @throws MailException When the DSN names no transport Symfony has.
	 */
	private function transport(): TransportInterface
	{
		if ($this->transport !== null) {
			return $this->transport;
		}

		$dsn = $this->mail->dsn();

		if ($dsn === null) {
			throw MailNotSetUp::make();
		}

		try {
			return $this->transport = Transport::fromDsn($dsn);
		} catch (Throwable $error) {
			throw new MailException(sprintf('The mail transport "%s" can\'t be used: %s', $this->mail->describe(), $error->getMessage()), 0, $error);
		}
	}

	private static function address(Address $address): MimeAddress
	{
		return new MimeAddress($address->email, $address->name);
	}
}
