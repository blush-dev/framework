<?php

/**
 * Notification registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Notification;

use Override;
use Blush\Job\JobRegistry;
use Blush\Support\Registry;
use Blush\Support\RegistrationException;

/**
 * Notification classes by key (D-846), `vendor/name` as jobs' are
 * (`core/password-link`, `acme/order-shipped`), so a queued one names
 * what it sends without naming a class.
 *
 * @extends Registry<Notification>
 */
final class NotificationRegistry extends Registry
{
	/**
	 * @inheritDoc
	 */
	protected const string CONTRACT = Notification::class;

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function register(string $key, string $className): void
	{
		if (preg_match(JobRegistry::KEY, $key) !== 1) {
			throw new RegistrationException(sprintf('A notification\'s key is "vendor/name" in lowercase letters, digits, and hyphens; "%s" given.', $key));
		}

		parent::register($key, $className);
	}
}
