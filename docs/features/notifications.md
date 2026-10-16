# Notifications

A notification tells people that something happened: an order shipped,
an import finished. It's sent by email, from a
[background job](background-jobs.md), in each person's language.

## Writing a notification

Extend `Blush\Notification\Notification`:

```php
namespace Acme\Shop;

use Blush\Notification\MailContent;
use Blush\Notification\Notifiable;
use Blush\Notification\Notification;
use Blush\Translation\Messages;

final class OrderShipped extends Notification
{
	public function __construct(
		private readonly Orders $orders,
		private readonly Messages $messages
	) {}

	public function label(): string
	{
		return 'Shipped orders';
	}

	public function mail(Notifiable $to, array $data): ?MailContent
	{
		$order = $this->orders->find($data['order']);

		if ($order === null) {
			return null;
		}

		return new MailContent($this->messages->t('order_shipped.subject', 'Your order shipped'))
			->line($this->messages->t('order_shipped.hello', 'Hi {name},', name: $to->name()))
			->line($this->messages->t('order_shipped.body', 'Order {number} is on its way.', number: $order->number))
			->action($this->messages->t('order_shipped.track', 'Track It'), $order->trackingUrl)
			->note($this->messages->t('order_shipped.why', 'You ordered from our shop.'));
	}
}
```

Register it under a `vendor/name` key from your plugin's
[provider's](../plugins/service-providers.md) `boot()`, and send it from
anywhere with the notifier:

```php
use Blush\Notification\NotificationRegistry;
use Blush\Notification\Notifier;
use Blush\Notification\Recipient;

$this->container->get(NotificationRegistry::class)->register('acme/order-shipped', OrderShipped::class);

$notifier->send('acme/order-shipped', Recipient::account($account), ['order' => $order->id]);
```

## Good to know

- **Recipients** are `Recipient::account()` (an account, or its `id`),
  `Recipient::address('orders@example.com')`, or
  `Recipient::capability('acme.orders')`: every account with that
  capability when it's sent, with an email address, that isn't
  suspended or token-only (see [Accounts](../concepts/accounts/README.md)).
  Pass a list for more than one. An account that's suspended, gone, or
  without an email address is sent nothing.
- **Data** is ids and plain values, as a job's is, since it's kept
  until it's sent. `mail()` reads what it needs by id then, so it says
  what's true when it's sent, and returns `null` to send nothing (the
  order was deleted).
- **The email** is parts Blush draws, as HTML and as text, the same
  for every notification: `line()` (a paragraph), `action()` (a button
  and its link), and `note()` (small print under a rule). Text is
  escaped when it's drawn. Themes don't change it.
- **Language:** `mail()` runs in the recipient's language (their
  account's, else the site's), so `t()` answers in it. Translate your
  text with your [catalogs](translations.md) as you would any other.
- **One at a time:** override `unique()` to return a key, and a second
  copy to the same person isn't queued while one waits.
- **Right away:** override `isUrgent()` to return `true` when someone is
  waiting on the email (a password link is). Its jobs run right after
  the response that sent it, rather than when a runner next comes by,
  on servers that can finish a response first (PHP-FPM, as most hosts
  run); elsewhere they wait like any other job.
- **`account:`** on `send()` records who caused it, for the Jobs screen.

## Sending and failing

Each recipient is a job (`core/notify`): it's tried again when the mail
server fails, and shown on **Tools › Jobs** when it fails for good.

Without a mail transport (none set, PHP has no mail program, or
`MAIL_DSN` is empty; see [Configuration](../reference/configuration/README.md)),
`send()` queues nothing, so check `$notifier->isOn()` before offering
something that needs email.

Each email sent is logged with its recipient and subject, at the `info`
level, so it's in the log when `config/log.php`'s `level` is
`Level::Info` or lower (the default keeps warnings and worse).

## A mail service of your own

To send through a service's API instead of SMTP, bind your own
`Blush\Mail\Mailer` in your provider's `register()`. It's handed a
`Blush\Mail\Message` (`to`, `subject`, `text`, `html`, `from`,
`replyTo`, `attachments`) and throws `Blush\Mail\MailException` when it
can't send; read its credentials from a [secret](secrets.md).

## Testing code that sends notifications

Bind `Blush\Mail\Testing\RecordingMailer` in place of the mailer to
keep what's sent in memory, and run the jobs (or use the `sync`
runner):

```php
use Blush\Mail\Mailer;
use Blush\Mail\Testing\RecordingMailer;

$mailer = new RecordingMailer();
$container->instance(Mailer::class, $mailer);

// … send, and run the queued jobs …

$this->assertCount(1, $mailer->sentTo('jane@example.com'));
```

`failWith('Connection refused')` makes it throw, as a server that's
down would.
