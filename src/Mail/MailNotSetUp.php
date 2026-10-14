<?php

/**
 * Mail not set up exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mail;

/**
 * The site has no way to send mail: no transport is set and PHP has no
 * mail program, or `MAIL_DSN` is empty.
 */
final class MailNotSetUp extends MailException
{
	public static function make(): self
	{
		return new self('Mail isn\'t set up: set MAIL_DSN in .env to a transport, such as an SMTP server.');
	}
}
