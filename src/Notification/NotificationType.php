<?php

/**
 * Notification type.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Notification;

use Blush\Backup\BackupNotification;
use Blush\Notification\Notifications\EmailChangeNotification;
use Blush\Notification\Notifications\PasswordLinkNotification;

/**
 * The framework's notifications, keyed by `core/{name}` (D-714, D-846).
 * More are added as the features that send them are built (D-832).
 */
enum NotificationType: string
{
	case EmailChange  = 'core/email-change';
	case PasswordLink = 'core/password-link';
	case Backup       = 'core/backup';

	/**
	 * Returns the notification's class.
	 *
	 * @return class-string<Notification>
	 */
	public function className(): string
	{
		return match ($this) {
			self::EmailChange  => EmailChangeNotification::class,
			self::PasswordLink => PasswordLinkNotification::class,
			self::Backup       => BackupNotification::class
		};
	}
}
