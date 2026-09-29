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

use Override;
use Blush\Admin\Action\AdminActionRegistry;
use Blush\Admin\Action\AdminActions;
use Blush\Admin\Action\AdminActionType;
use Blush\Core\ServiceProvider;
use Blush\Routing\RouteSource;

/**
 * Adds the admin's routes, which exist only while `AdminConfig::$enabled`
 * is on (D-013), and the action registry, seeded with the built-ins.
 * Extensions add actions from their own `boot()` (D-222).
 */
final class AdminServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		AdminApp::class,
		AdminActions::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TRANSIENTS = [
		AdminRoutes::class,
		ActionController::class,
		AssetController::class,
		DashboardController::class,
		SessionController::class,
		ShellController::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TAGS = [
		RouteSource::TAG => [AdminRoutes::class]
	];

	/**
	 * Binds the action registry, seeded with the built-ins.
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singleton(AdminActionRegistry::class, static function (): AdminActionRegistry {
			$registry = new AdminActionRegistry();

			foreach (AdminActionType::cases() as $type) {
				$registry->registerIf($type->value, $type->className());
			}

			return $registry;
		});
	}
}
