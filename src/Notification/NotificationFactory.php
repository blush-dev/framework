<?php

/**
 * Notification factory.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Notification;

use Throwable;
use Blush\Container\Container;

/**
 * Builds registered notifications through the container, so a
 * notification's constructor can ask for services.
 */
final readonly class NotificationFactory
{
	public function __construct(
		private NotificationRegistry $registry,
		private Container $container
	) {}

	/**
	 * Builds a notification.
	 *
	 * @throws UnknownNotification When nothing is registered under the key.
	 * @throws NotificationException When it can't be built.
	 */
	public function make(string $key): Notification
	{
		$class = $this->registry->get($key) ?? throw UnknownNotification::named($key);

		try {
			return $this->container->make($class);
		} catch (Throwable $error) {
			throw new NotificationException(sprintf('Unable to build the "%s" notification: %s', $key, $error->getMessage()), 0, $error);
		}
	}
}
