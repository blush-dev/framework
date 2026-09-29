<?php

/**
 * Admin service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Blush\Core\ServiceProvider;
use Blush\Routing\RouteSource;

/**
 * Adds the admin's routes, which exist only while `AdminConfig::$enabled`
 * is on (D-013).
 */
final class AdminServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array TRANSIENTS = [
		AdminRoutes::class,
		SessionController::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TAGS = [
		RouteSource::TAG => [AdminRoutes::class]
	];
}
