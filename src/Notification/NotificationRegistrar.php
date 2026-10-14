<?php

/**
 * Notification registrar.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Notification;

use Blush\Support\RegistrationException;

/**
 * Seeds the registry with the framework's notifications, leaving any key
 * an extension took first alone.
 */
final readonly class NotificationRegistrar
{
	public function __construct(private NotificationRegistry $registry)
	{}

	/**
	 * Registers each built-in notification whose key is still free.
	 *
	 * @throws RegistrationException
	 */
	public function register(): void
	{
		foreach (NotificationType::cases() as $type) {
			$this->registry->registerIf($type->value, $type->className());
		}
	}
}
