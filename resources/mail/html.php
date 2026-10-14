<?php

/**
 * A notification's email, its HTML part (`Notification\MailLayout`,
 * D-846): the site's name, the email's parts, and a footer saying who
 * sent it. Plain and narrow, with its styles inline and in tables, as
 * mail apps need; core's, never a theme's (D-832).
 *
 * @var Blush\Notification\MailContent       $content
 * @var Blush\Notification\Notifiable        $to
 * @var array{name: string, url: string}     $site
 * @var Blush\Translation\Messages           $messages
 */

declare(strict_types=1);

use Blush\Notification\MailPartKind;

$font = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";

?>
<!doctype html>
<html lang="<?= attr(str_replace('_', '-', $to->language() ?? 'en')) ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="color-scheme" content="light">
	<title><?= e($content->subject) ?></title>
</head>
<body style="margin: 0; padding: 0; background: #f4f4f5;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #f4f4f5;">
		<tr>
			<td align="center" style="padding: 32px 16px;">
				<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 560px;">
					<tr>
						<td style="padding: 0 4px 16px; font-family: <?= attr($font) ?>; font-size: 15px; font-weight: 600; color: #3f3f46;"><?= e($site['name']) ?></td>
					</tr>
					<tr>
						<td style="padding: 32px; background: #ffffff; border-radius: 8px; font-family: <?= attr($font) ?>; font-size: 16px; line-height: 1.6; color: #18181b;">
							<?php foreach ($content->parts as $part) : ?>
								<?php if ($part->kind === MailPartKind::Line) : ?>
									<p style="margin: 0 0 16px;"><?= nl2br(e($part->text)) ?></p>
								<?php elseif ($part->kind === MailPartKind::Action) : ?>
									<table role="presentation" cellpadding="0" cellspacing="0" style="margin: 24px 0;">
										<tr>
											<td style="border-radius: 6px; background: #18181b;">
												<a href="<?= url($part->url) ?>" style="display: inline-block; padding: 12px 24px; font-family: <?= attr($font) ?>; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none;"><?= e($part->text) ?></a>
											</td>
										</tr>
									</table>
									<p style="margin: 0 0 16px; font-size: 13px; line-height: 1.5; color: #71717a; word-break: break-all;"><?= e($messages->t('notification.mail_layout.link_fallback', 'Or open this link:')) ?> <a href="<?= url($part->url) ?>" style="color: #3f3f46;"><?= e($part->url) ?></a></p>
								<?php else : ?>
									<p style="margin: 24px 0 0; padding-top: 16px; border-top: 1px solid #e4e4e7; font-size: 13px; line-height: 1.5; color: #71717a;"><?= nl2br(e($part->text)) ?></p>
								<?php endif ?>
							<?php endforeach ?>
						</td>
					</tr>
					<tr>
						<td style="padding: 16px 4px 0; font-family: <?= attr($font) ?>; font-size: 12px; line-height: 1.5; color: #71717a;"><?= e($messages->t('notification.mail_layout.footer', 'Sent by {site}, {url}', site: $site['name'], url: $site['url'])) ?></td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
