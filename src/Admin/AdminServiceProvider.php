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
 * is on (D-013). The Tools screen's buttons are tools (`Blush\Tool`,
 * D-761), which extensions add from their own `boot()`.
 */
final class AdminServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		AdminApp::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TRANSIENTS = [
		AdminRoutes::class,
		ActivityController::class,
		AuthorizeController::class,
		AssetController::class,
		DashboardController::class,
		EntriesController::class,
		EntryController::class,
		EntryHandles::class,
		HealthController::class,
		PreviewLinkController::class,
		SessionController::class,
		ShellController::class,
		ToolController::class
	];

	/**
	 * Where Site Health keeps its last report (D-545) and the problems
	 * ignored (D-613), unless something bound another store first.
	 *
	 * @inheritDoc
	 */
	protected const array SINGLETONS_IF = [
		HealthReportStore::class => FileHealthReportStore::class,
		IgnoredProblems::class   => StoredIgnoredProblems::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TAGS = [
		RouteSource::TAG => [AdminRoutes::class]
	];
}
