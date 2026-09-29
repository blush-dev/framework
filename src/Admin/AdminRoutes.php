<?php

/**
 * Admin routes.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Override;
use Blush\Auth\Middleware\Authenticate;
use Blush\Auth\Middleware\VerifyCsrf;
use Blush\Routing\Route;
use Blush\Routing\RoutePriority;
use Blush\Routing\RouteSource;
use Blush\Session\StartSession;

/**
 * The admin's routes, only while the admin is on:
 *
 * - `GET {path}` and every screen under it: the app's page.
 * - `GET {path}/assets/{file}`: the app's built files.
 * - The JSON API, under `{path}/api`. Every API route starts a session
 *   and checks CSRF; all but signing in (and asking who's signed in)
 *   need an account.
 *   - `GET  session`: the signed-in account, or `null`.
 *   - `POST login`: signs in (`{"username", "password"}`).
 *   - `POST logout`: signs out.
 *   - `GET  dashboard`: the site, content counts, and the actions the
 *     account may run.
 *   - `POST actions/{action}`: runs an action.
 */
final readonly class AdminRoutes implements RouteSource
{
	public function __construct(private AdminConfig $config)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function priority(): RoutePriority
	{
		return RoutePriority::System;
	}

	/**
	 * @inheritDoc
	 * @return list<Route>
	 */
	#[Override]
	public function routes(): iterable
	{
		if (! $this->config->enabled) {
			return [];
		}

		$api = Route::group("{$this->config->path}/api", [
			Route::get('/session', [SessionController::class, 'show'])->named('session'),
			Route::post('/login', [SessionController::class, 'login'])->named('login'),
			Route::post('/logout', [SessionController::class, 'logout'])->named('logout')->middleware(Authenticate::class),
			Route::get('/dashboard', DashboardController::class)->named('dashboard')->middleware(Authenticate::class),
			Route::post('/actions/{action:[a-z0-9][a-z0-9-]*}', ActionController::class)->named('action')->middleware(Authenticate::class)
		], name: 'admin.api.', middleware: [StartSession::class, VerifyCsrf::class]);

		$app = Route::group($this->config->path, [
			Route::get('/assets/{file:.+}', AssetController::class)->named('asset'),
			Route::get('/', ShellController::class)->named('app'),
			Route::get('/{screen:(?!api/|assets/)[A-Za-z0-9_/-]+}', ShellController::class)->named('screen')
		], name: 'admin.');

		return [...$api, ...$app];
	}
}
