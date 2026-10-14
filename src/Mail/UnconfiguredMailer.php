<?php

/**
 * Unconfigured mailer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mail;

use Override;

/**
 * The mailer of a site with no transport (`MailTransport::isSetUp()` is
 * false): it refuses every message, so a caller finds out rather than
 * mail going nowhere. Notifications check first and skip quietly.
 */
final readonly class UnconfiguredMailer implements Mailer
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function send(Message $message): void
	{
		throw MailNotSetUp::make();
	}
}
