<?php

/**
 * Mailer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mail;

/**
 * Sends a message through the site's transport (D-832, `MailTransport`):
 * `SymfonyMailer` for SMTP, PHP's own mail, sendmail, and `null`,
 * `LogMailer` for `log`, and `UnconfiguredMailer` when there's none. A
 * plugin for a mail service binds its own.
 *
 * Callers send notifications (`Blush\Notification\Notifier`), never a
 * mailer directly; Settings → Email's **Send a Test** is the exception,
 * since it tests the transport itself.
 */
interface Mailer
{
	/**
	 * Sends a message, from the site's sender unless it names another.
	 *
	 * @throws MailException When it can't be sent.
	 */
	public function send(Message $message): void;
}
