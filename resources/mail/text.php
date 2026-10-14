<?php

/**
 * A notification's email, its text part (`Notification\MailLayout`,
 * D-846): the email's parts as plain paragraphs, a button as its label
 * and link, and a footer saying who sent it. Nothing is escaped: it's
 * text, not HTML.
 *
 * @var Blush\Notification\MailContent       $content
 * @var Blush\Notification\Notifiable        $to
 * @var array{name: string, url: string}     $site
 * @var Blush\Translation\Messages           $messages
 */

declare(strict_types=1);

use Blush\Notification\MailPartKind;

foreach ($content->parts as $part) {
	echo match ($part->kind) {
		MailPartKind::Line   => $part->text,
		MailPartKind::Action => $part->text . ":\n" . $part->url,
		MailPartKind::Note   => "--\n" . $part->text
	}, "\n\n";
}

echo "--\n", $messages->t('notification.mail_layout.footer', 'Sent by {site}, {url}', site: $site['name'], url: $site['url']), "\n";
