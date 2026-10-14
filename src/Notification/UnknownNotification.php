<?php

/**
 * Unknown notification exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Notification;

/**
 * No notification is registered under a key.
 */
final class UnknownNotification extends NotificationException
{
	public static function named(string $key): self
	{
		return new self(sprintf('No notification is registered as "%s".', $key));
	}
}
