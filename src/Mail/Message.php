<?php

/**
 * Mail message.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mail;

/**
 * One email (D-832): who it's to, its subject, a text part and an HTML
 * part, and any attachments. `from` is left out for the site's sender
 * (`MailConfig::sender()`), which the mailer fills in.
 *
 *     $mailer->send(new Message(
 *         to: [new Address('jane@example.com', 'Jane')],
 *         subject: 'Hello',
 *         text: 'Hello, Jane.',
 *         html: '<p>Hello, Jane.</p>'
 *     ));
 *
 * Nothing outside `Blush\Mail` builds a message for a person: core and
 * plugins send notifications (`Blush\Notification`), which render one.
 */
final readonly class Message
{
	/**
	 * @param list<Address>    $to
	 * @param list<Attachment> $attachments
	 * @throws MailException When it's to no one, or the subject is empty or
	 *                       more than one line.
	 */
	public function __construct(
		public array $to,
		public string $subject,
		public string $text,
		public string $html = '',
		public ?Address $from = null,
		public ?Address $replyTo = null,
		public array $attachments = []
	) {
		if ($to === []) {
			throw new MailException('A message is to someone.');
		}

		if (trim($subject) === '' || preg_match('/[\r\n]/', $subject) === 1) {
			throw new MailException('A message\'s subject is one line of text.');
		}
	}

	/**
	 * Returns a copy from an address.
	 */
	#[\NoDiscard]
	public function withFrom(Address $from): self
	{
		return clone($this, ['from' => $from]);
	}
}
