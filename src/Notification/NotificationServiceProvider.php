<?php

/**
 * Notification service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Notification;

use Override;
use Blush\Core\ServiceProvider;

/**
 * Binds notifications (D-832, D-846): the registry, seeded with the
 * framework's, its factory, the mail layout, and the notifier. Extensions
 * register their own in their `boot()`:
 *
 *     $container->get(NotificationRegistry::class)->register('acme/order-shipped', OrderShipped::class);
 */
final class NotificationServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		NotificationFactory::class,
		MailLayout::class,
		Notifier::class
	];

	/**
	 * Binds the registry, seeded.
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singleton(NotificationRegistry::class, static function (): NotificationRegistry {
			$registry = new NotificationRegistry();
			new NotificationRegistrar($registry)->register();

			return $registry;
		});
	}
}
