<?php

/**
 * Hello notification fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Notification;

use Override;
use Blush\Notification\MailContent;
use Blush\Notification\Notifiable;
use Blush\Notification\Notification;
use Blush\Translation\CurrentLocale;

/**
 * Says hello in a subject naming the language it was written in, with
 * `data.note` as a line; `data.skip` sends nothing.
 */
final class HelloNotification extends Notification
{
	public function __construct(private readonly CurrentLocale $locale)
	{}

	#[Override]
	public function label(): string
	{
		return 'Hello';
	}

	#[Override]
	public function mail(Notifiable $to, array $data): ?MailContent
	{
		if (($data['skip'] ?? false) === true) {
			return null;
		}

		return new MailContent("Hello in {$this->locale->get()}")
			->line("Hi {$to->name()},")
			->line(is_string($data['note'] ?? null) ? $data['note'] : '<b>bold</b> & more')
			->action('Open', 'https://example.test/open?a=1&b=2')
			->note('Small print');
	}
}
