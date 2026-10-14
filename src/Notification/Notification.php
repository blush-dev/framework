<?php

/**
 * Notification.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Notification;

use Stringable;

/**
 * Something happened that people should hear about (D-832): a password
 * link, a failed job, a comment. Registered by key (`core/password-link`,
 * `acme/order-shipped`) and sent with `Notifier::send()`, to recipients,
 * with data (ids and scalars) that say what happened:
 *
 *     $notifier->send('acme/order-shipped', Recipient::account($id), ['order' => $order->id]);
 *
 * Each recipient's message is rendered when it's sent, by a job (D-621),
 * in their language (D-831): `mail()` is called with `t()` already
 * answering in it, and reads what it needs by id then, so a title
 * changed in the meantime is the new one. Email is the one channel so
 * far.
 *
 * A notification is built through the container, so its constructor can
 * ask for services.
 */
abstract class Notification
{
	/**
	 * Returns what it's about, for lists ("Password links").
	 */
	abstract public function label(): string|Stringable;

	/**
	 * Returns the email for one recipient, or `null` to send them none
	 * (what it's about is gone, or doesn't apply to them).
	 *
	 * @param  array<string, mixed> $data
	 * @throws \Throwable When it fails in a way that trying again may fix.
	 */
	abstract public function mail(Notifiable $to, array $data): ?MailContent;

	/**
	 * Whether someone is waiting on it (D-847), as on a password link: its
	 * jobs run right after the response that sent it, rather than when a
	 * runner next comes by.
	 */
	public function isUrgent(): bool
	{
		return false;
	}

	/**
	 * Returns a key that keeps a second copy to the same recipient out of
	 * the queue while one waits, or `null` for none: a password link asked
	 * for twice is sent once.
	 *
	 * @param array<string, mixed> $data
	 */
	public function unique(array $data): ?string
	{
		return null;
	}
}
