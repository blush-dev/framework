<?php

/**
 * Recording mailer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mail\Testing;

use Override;
use Blush\Mail\Address;
use Blush\Mail\Mailer;
use Blush\Mail\MailException;
use Blush\Mail\Message;

/**
 * A mailer for tests (D-846): it keeps what's sent in memory, so a test
 * can check what its code sent. Bind it in place of the mailer before
 * the code under test asks for one:
 *
 *     $mailer = new RecordingMailer();
 *     $container->instance(Mailer::class, $mailer);
 *
 *     // … run the code …
 *
 *     $this->assertCount(1, $mailer->sentTo('jane@example.com'));
 *
 * `failWith()` makes the next sends throw, as a transport that's down
 * would.
 */
final class RecordingMailer implements Mailer
{
	/**
	 * Every message sent, in order.
	 *
	 * @var list<Message>
	 */
	private array $sent = [];

	/**
	 * What sending throws, or `null` while it works.
	 */
	private ?string $failure = null;

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function send(Message $message): void
	{
		if ($this->failure !== null) {
			throw new MailException($this->failure);
		}

		$this->sent[] = $message;
	}

	/**
	 * Returns the messages sent, to an address or to anyone.
	 *
	 * @return list<Message>
	 */
	public function sent(): array
	{
		return $this->sent;
	}

	/**
	 * Returns the messages sent to an address, in any case.
	 *
	 * @return list<Message>
	 */
	public function sentTo(string $email): array
	{
		return array_values(array_filter($this->sent, static fn (Message $message): bool => array_any($message->to, static fn (Address $to): bool => mb_strtolower($to->email) === mb_strtolower($email))));
	}

	/**
	 * Makes every send throw with a message, or work again with `null`.
	 */
	public function failWith(?string $failure): void
	{
		$this->failure = $failure;
	}

	/**
	 * Forgets everything sent.
	 */
	public function clear(): void
	{
		$this->sent = [];
	}
}
