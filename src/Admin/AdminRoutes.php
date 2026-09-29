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
 * The admin's JSON API, under `{path}/api`, only while the admin is on.
 * Every route starts a session and checks CSRF; all but signing in (and
 * asking who's signed in) need an account.
 *
 * - `GET  {path}/api/session`: the signed-in account, or `null`.
 * - `POST {path}/api/login`: signs in (`{"username", "password"}`).
 * - `POST {path}/api/logout`: signs out.
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

		return Route::group("{$this->config->path}/api", [
			Route::get('/session', [SessionController::class, 'show'])->named('session'),
			Route::post('/login', [SessionController::class, 'login'])->named('login'),
			Route::post('/logout', [SessionController::class, 'logout'])->named('logout')->middleware(Authenticate::class)
		], name: 'admin.api.', middleware: [StartSession::class, VerifyCsrf::class]);
	}
}
