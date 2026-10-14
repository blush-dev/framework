<?php

/**
 * Log mailer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mail;

use Override;
use Psr\Log\LoggerInterface;
use Blush\Core\AppConfig;

/**
 * Writes each message to the site's log instead of sending it (`log`,
 * D-846), whole: its headers, text part, and HTML part, so mail can be
 * read in development without a mail server (Tools → Log, or the log
 * file). The only place a message's body is logged. It's written as a
 * warning, since it wasn't delivered, and so that it's written at all:
 * the log keeps warnings and worse by default (`LogConfig::$level`).
 */
final readonly class LogMailer implements Mailer
{
	public function __construct(
		private MailConfig $config,
		private AppConfig $app,
		private LoggerInterface $logger
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function send(Message $message): void
	{
		$from  = $message->from ?? $this->config->sender($this->app);
		$lines = [
			'From: ' . $from,
			'To: ' . implode(', ', array_map(strval(...), $message->to)),
			...($message->replyTo === null ? [] : ['Reply-To: ' . $message->replyTo]),
			'Subject: ' . $message->subject,
			...array_map(static fn (Attachment $attachment): string => "Attachment: {$attachment->name} ({$attachment->type}, " . strlen($attachment->contents) . ' bytes)', $message->attachments),
			'',
			$message->text,
			...($message->html === '' ? [] : ['', '--- HTML ---', $message->html])
		];

		$this->logger->warning("Mail not sent (log transport): \"{$message->subject}\"\n" . implode("\n", $lines));
	}
}
