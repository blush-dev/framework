<?php

/**
 * Mail layout.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Notification;

use Throwable;
use Blush\Core\AppConfig;
use Blush\Core\Framework;
use Blush\Translation\Messages;

/**
 * Draws a notification's email (`MailContent`) as its HTML part and its
 * text part (D-846), from core's layout: `resources/mail/html.php` and
 * `resources/mail/text.php`. What Blush sends is the code's, never the
 * theme's (D-832), so a theme can't change it; the layout is plain, with
 * its styles inline, as mail apps need.
 *
 * Its own text (the footer) is in the current language, which the job
 * sets to the recipient's.
 */
final readonly class MailLayout
{
	public function __construct(
		private AppConfig $app,
		private Messages $messages
	) {}

	/**
	 * Returns the email's HTML part.
	 */
	public function html(MailContent $content, Notifiable $to): string
	{
		return $this->render('html.php', $content, $to);
	}

	/**
	 * Returns the email's text part.
	 */
	public function text(MailContent $content, Notifiable $to): string
	{
		return trim(preg_replace("/\n{3,}/", "\n\n", $this->render('text.php', $content, $to)) ?? '') . "\n";
	}

	/**
	 * Renders one of the layout's files with the email's parts.
	 */
	private function render(string $file, MailContent $content, Notifiable $to): string
	{
		$level = ob_get_level();

		ob_start();

		try {
			(static function (string $__file, array $__data): void {
				extract($__data);

				require $__file;
			})(Framework::path("resources/mail/{$file}"), [
				'content'  => $content,
				'to'       => $to,
				'site'     => ['name' => $this->app->name, 'url' => $this->app->absoluteUrl('/')],
				'messages' => $this->messages
			]);
		} catch (Throwable $error) {
			while (ob_get_level() > $level) {
				ob_end_clean();
			}

			throw $error;
		}

		return (string) ob_get_clean();
	}
}
