<?php

/**
 * Mail content.
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
 * What a notification's email says (D-846), as parts core's mail layout
 * draws (`MailLayout`), as HTML and as text, so every message Blush and
 * its plugins send looks alike and reads well in any mail app:
 *
 *     return new MailContent($this->messages->t('acme.shipped.subject', 'Your order shipped'))
 *         ->line($this->messages->t('acme.shipped.hello', 'Hi {name},', name: $to->name()))
 *         ->line($this->messages->t('acme.shipped.body', 'It\'s on its way.'))
 *         ->action($this->messages->t('acme.shipped.track', 'Track It'), $url)
 *         ->note($this->messages->t('acme.shipped.why', 'You ordered from us.'));
 *
 * Text is taken as it's given, already in the recipient's language
 * (`Notification::mail()` runs in it), and escaped when it's drawn.
 */
final readonly class MailContent
{
	/**
	 * The subject line.
	 */
	public string $subject;

	/**
	 * @param list<MailPart> $parts
	 * @throws NotificationException When the subject is empty.
	 */
	public function __construct(
		string|Stringable $subject,
		public array $parts = []
	) {
		$subject = trim(preg_replace('/\s+/', ' ', (string) $subject) ?? '');

		if ($subject === '') {
			throw new NotificationException('An email needs a subject.');
		}

		$this->subject = $subject;
	}

	/**
	 * Adds a paragraph.
	 */
	#[\NoDiscard]
	public function line(string|Stringable $text): self
	{
		return $this->with(new MailPart(MailPartKind::Line, (string) $text));
	}

	/**
	 * Adds a button that opens a link (in text, the label and the link).
	 *
	 * @throws NotificationException When the link isn't http or https.
	 */
	#[\NoDiscard]
	public function action(string|Stringable $label, string $url): self
	{
		if (preg_match('#^https?://#i', $url) !== 1) {
			throw new NotificationException(sprintf('An email\'s button links to an http or https address; "%s" given.', $url));
		}

		return $this->with(new MailPart(MailPartKind::Action, (string) $label, $url));
	}

	/**
	 * Adds small print, under a rule: why they got it, or what to do if it
	 * wasn't them.
	 */
	#[\NoDiscard]
	public function note(string|Stringable $text): self
	{
		return $this->with(new MailPart(MailPartKind::Note, (string) $text));
	}

	/**
	 * Returns a copy with another part at the end.
	 */
	private function with(MailPart $part): self
	{
		return clone($this, ['parts' => [...$this->parts, $part]]);
	}
}
