<?php

/**
 * Notifier.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Notification;

use Psr\Log\LoggerInterface;
use Blush\Job\JobException;
use Blush\Job\JobQueue;
use Blush\Job\JobRecord;
use Blush\Job\JobType;
use Blush\Job\UrgentJobs;
use Blush\Mail\MailTransport;

/**
 * Sends notifications (D-832, D-846), the one way core and plugins send
 * mail:
 *
 *     $notifier->send('core/password-link', Recipient::account($account), ['reason' => 'reset']);
 *     $notifier->send('acme/low-stock', Recipient::capability('acme.orders'), ['product' => $id]);
 *
 * Nothing is sent in the request: each recipient is a job
 * (`core/notify`, `NotifyJob`), retried with backoff when the transport
 * fails, and shown on Tools → Jobs when it fails for good. A capability's
 * job queues one for each account that has it when it runs. An urgent
 * notification's jobs run right after the response (`UrgentJobs`,
 * D-847).
 *
 * A site with no mail transport (`MailTransport::isSetUp()`) queues
 * nothing; the skip is logged.
 */
final readonly class Notifier
{
	public function __construct(
		private NotificationFactory $factory,
		private JobQueue $queue,
		private UrgentJobs $urgent,
		private MailTransport $mail,
		private LoggerInterface $logger
	) {}

	/**
	 * Whether notifications are sent at all: the site can send mail.
	 */
	public function isOn(): bool
	{
		return $this->mail->isSetUp();
	}

	/**
	 * Queues a notification for each recipient, and returns their jobs
	 * (one already waiting under the notification's `unique` key is
	 * returned in place of a second). `$account` is who caused it, for
	 * the Jobs screen. Returns none when the site can't send mail.
	 *
	 * @param  Recipient|list<Recipient> $to
	 * @param  array<string, mixed>      $data Ids and scalars, in arrays.
	 * @return list<JobRecord>
	 * @throws UnknownNotification When nothing is registered under the key.
	 * @throws NotificationException When it can't be built, or its data
	 *                               isn't plain values.
	 * @throws JobException When a job can't be saved.
	 */
	public function send(string $notification, Recipient|array $to, array $data = [], ?string $account = null): array
	{
		$made = $this->factory->make($notification);

		if (! JobRecord::isPlain($data)) {
			throw new NotificationException(sprintf('The "%s" notification\'s data must be ids and scalars, in arrays.', $notification));
		}

		if (! $this->isOn()) {
			$this->logger->info('Mail: "{notification}" wasn\'t sent, since the site has no mail transport.', ['notification' => $notification]);

			return [];
		}

		$unique = $made->unique($data);
		$jobs   = [];

		foreach ($to instanceof Recipient ? [$to] : $to as $recipient) {
			$job = $this->queue->push(
				JobType::Notify->value,
				['notification' => $notification, 'recipient' => $recipient->toArray(), 'data' => $data],
				$account,
				$unique === null ? null : "notify:{$notification}:{$unique}:{$recipient->key()}"
			);

			if ($made->isUrgent()) {
				$this->urgent->add($job);
			}

			$jobs[] = $job;
		}

		return $jobs;
	}
}
