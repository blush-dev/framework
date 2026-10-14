<?php

/**
 * Notify job.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Notification;

use Override;
use Psr\Log\LoggerInterface;
use Blush\Auth\Account;
use Blush\Auth\Accounts;
use Blush\Auth\Permissions;
use Blush\Job\Job;
use Blush\Job\JobRecord;
use Blush\Job\JobResult;
use Blush\Mail\Address;
use Blush\Mail\MailTransport;
use Blush\Mail\Mailer;
use Blush\Mail\MailNotSetUp;
use Blush\Mail\Message;
use Blush\Translation\CurrentLocale;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Sends one notification to one recipient (`core/notify`, D-846), as
 * `Notifier::send()` queued it: `{"notification", "recipient", "data"}`.
 *
 * - An account is read now, by id; one that's gone, suspended, or
 *   without an email address gets nothing, and the job is done.
 * - A capability queues a job for each account that has it now, with an
 *   email address, that isn't suspended or token-only.
 * - The email is rendered in the recipient's language (the account's,
 *   D-831, else the site's): the notification's parts, then core's
 *   layout (`MailLayout`).
 * - A transport that fails throws, so the job is tried again with
 *   backoff (D-621); after its attempts it's failed on Tools → Jobs.
 *   A site whose transport was taken away fails it at once.
 *
 * Each send is logged with its recipient and subject, never its body
 * (D-846).
 */
final class NotifyJob extends Job
{
	public function __construct(
		private readonly NotificationFactory $factory,
		private readonly Notifier $notifier,
		private readonly Accounts $accounts,
		private readonly Permissions $permissions,
		private readonly Mailer $mailer,
		private readonly MailTransport $mail,
		private readonly MailLayout $layout,
		private readonly CurrentLocale $locale,
		private readonly LoggerInterface $logger,
		private readonly Messages $messages
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): Text
	{
		return $this->messages->t('notification.notify_job.label', 'Send a notification');
	}

	/**
	 * Names the notification and who it's to.
	 */
	#[Override]
	public function describe(JobRecord $job): string
	{
		$key       = is_string($job->data['notification'] ?? null) ? $job->data['notification'] : '';
		$recipient = Recipient::fromArray($job->data['recipient'] ?? null);

		try {
			$label = (string) $this->factory->make($key)->label();
		} catch (NotificationException) {
			$label = $key;
		}

		return (string) match ($recipient?->kind) {
			RecipientKind::Account    => $this->messages->t('notification.notify_job.describe_account', 'Send "{notification}" to {name}', notification: $label, name: $this->nameOf($recipient->value)),
			RecipientKind::Address    => $this->messages->t('notification.notify_job.describe_address', 'Send "{notification}" to {email}', notification: $label, email: $recipient->value),
			RecipientKind::Capability => $this->messages->t('notification.notify_job.describe_capability', 'Send "{notification}" to everyone with {capability}', notification: $label, capability: $recipient->value),
			null                      => $this->label()
		};
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function handle(JobRecord $job): JobResult
	{
		$key       = $job->data['notification'] ?? null;
		$recipient = Recipient::fromArray($job->data['recipient'] ?? null);
		$data      = $job->data['data'] ?? [];

		if (! is_string($key) || $recipient === null || ! is_array($data)) {
			return JobResult::failed($this->messages->t('notification.notify_job.damaged', 'This job doesn\'t say what to send, or to whom.'));
		}

		/** @var array<string, mixed> $data */

		try {
			$notification = $this->factory->make($key);
		} catch (UnknownNotification) {
			return JobResult::failed($this->messages->t('notification.notify_job.unknown', 'Nothing sends "{notification}" any more; its plugin may be off.', notification: $key));
		}

		if (! $this->mail->isSetUp()) {
			return JobResult::failed($this->messages->t('notification.notify_job.no_transport', 'The site can\'t send mail: it has no transport.'));
		}

		if ($recipient->kind === RecipientKind::Capability) {
			return $this->fanOut($key, $recipient->value, $data, $job->account);
		}

		$to = $this->notifiable($recipient);

		if ($to === null) {
			return JobResult::done($this->messages->t('notification.notify_job.no_recipient', 'Not sent: the account is gone, suspended, or has no email address.'));
		}

		return $this->locale->in($to->language(), fn (): JobResult => $this->sendTo($notification, $key, $to, $data));
	}

	/**
	 * Renders and sends the email, in the recipient's language.
	 *
	 * @param array<string, mixed> $data
	 */
	private function sendTo(Notification $notification, string $key, Notifiable $to, array $data): JobResult
	{
		$content = $notification->mail($to, $data);

		if ($content === null) {
			return JobResult::done($this->messages->t('notification.notify_job.nothing', 'Nothing to send to {email}.', email: $to->address->email));
		}

		$message = new Message(
			to: [$to->address],
			subject: $content->subject,
			text: $this->layout->text($content, $to),
			html: $this->layout->html($content, $to)
		);

		try {
			$this->mailer->send($message);
		} catch (MailNotSetUp) {
			return JobResult::failed($this->messages->t('notification.notify_job.no_transport', 'The site can\'t send mail: it has no transport.'));
		}

		$this->logger->info('Mail: sent "{notification}" ("{subject}") to {email}.', ['notification' => $key, 'subject' => $content->subject, 'email' => $to->address->email]);

		return JobResult::done($this->messages->t('notification.notify_job.sent', 'Sent to {email}', email: $to->address->email));
	}

	/**
	 * Queues the notification for each account with a capability.
	 *
	 * @param array<string, mixed> $data
	 */
	private function fanOut(string $key, string $capability, array $data, ?string $account): JobResult
	{
		$recipients = [];

		foreach ($this->accounts->all() as $candidate) {
			if (! $candidate->tokenOnly && self::reachable($candidate) && $this->permissions->can($candidate, $capability)) {
				$recipients[] = Recipient::account($candidate);
			}
		}

		$this->notifier->send($key, $recipients, $data, $account);

		return JobResult::done($this->messages->t('notification.notify_job.fanned_out', 'Queued for {count, plural, =0 {no accounts} one {# account} other {# accounts}}', count: count($recipients)));
	}

	/**
	 * Returns who an account or address recipient is, or `null` for an
	 * account that can't be sent mail.
	 */
	private function notifiable(Recipient $recipient): ?Notifiable
	{
		if ($recipient->kind === RecipientKind::Address) {
			return new Notifiable(new Address($recipient->value));
		}

		$account = $this->accounts->findById($recipient->value);

		return $account !== null && self::reachable($account) && $account->email !== null
			? new Notifiable(new Address($account->email, str_replace(["\r", "\n"], ' ', $account->name ?? '')), $account)
			: null;
	}

	/**
	 * Whether an account can be sent mail: it has an address and isn't
	 * suspended.
	 */
	private static function reachable(Account $account): bool
	{
		return $account->email !== null && $account->email !== '' && ! $account->suspended;
	}

	/**
	 * Returns an account's name for a job's description.
	 */
	private function nameOf(string $id): string
	{
		$account = $this->accounts->findById($id);

		return $account === null ? $id : ($account->name ?? $account->username);
	}
}
