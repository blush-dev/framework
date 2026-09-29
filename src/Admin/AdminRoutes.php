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
 *   - `PATCH preferences`: changes the account's own preferences.
 *   - `GET  dashboard`: the site, content counts, and the actions the
 *     account may run.
 *   - `POST actions/{action}`: runs an action.
 *   - `GET  types`: the site's content types.
 *   - `GET  entries`: the entries the account may edit, a page at a time.
 *   - `POST entries`, and `GET`, `PATCH`, and `DELETE entries/{id}`:
 *     the editing API (`EntryController`).
 *   - `GET  trash`, and `POST trash/restore`, `trash/delete`, and
 *     `trash/empty`: the trash (`TrashController`).
 *   - `GET  health`: the content's lint problems.
 *   - `POST previews`: a signed preview link to an entry.
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
			Route::patch('/preferences', PreferencesController::class)->named('preferences')->middleware(Authenticate::class),
			Route::get('/dashboard', DashboardController::class)->named('dashboard')->middleware(Authenticate::class),
			Route::post('/actions/{action:[a-z0-9][a-z0-9-]*}', ActionController::class)->named('action')->middleware(Authenticate::class),
			Route::get('/types', TypesController::class)->named('types')->middleware(Authenticate::class),
			Route::get('/entries', EntriesController::class)->named('entries')->middleware(Authenticate::class),
			Route::post('/entries', [EntryController::class, 'create'])->named('entry.create')->middleware(Authenticate::class),
			Route::get('/entries/{id:.+}', [EntryController::class, 'show'])->named('entry')->middleware(Authenticate::class),
			Route::patch('/entries/{id:.+}', [EntryController::class, 'update'])->named('entry.update')->middleware(Authenticate::class),
			Route::delete('/entries/{id:.+}', [EntryController::class, 'delete'])->named('entry.delete')->middleware(Authenticate::class),
			Route::get('/trash', [TrashController::class, 'index'])->named('trash')->middleware(Authenticate::class),
			Route::post('/trash/restore', [TrashController::class, 'restore'])->named('trash.restore')->middleware(Authenticate::class),
			Route::post('/trash/delete', [TrashController::class, 'delete'])->named('trash.delete')->middleware(Authenticate::class),
			Route::post('/trash/empty', [TrashController::class, 'empty'])->named('trash.empty')->middleware(Authenticate::class),
			Route::get('/health', HealthController::class)->named('health')->middleware(Authenticate::class),
			Route::post('/previews', PreviewLinkController::class)->named('preview')->middleware(Authenticate::class)
		], name: 'admin.api.', middleware: [StartSession::class, VerifyCsrf::class]);

		$app = Route::group($this->config->path, [
			Route::get('/assets/{file:.+}', AssetController::class)->named('asset'),
			Route::get('/', ShellController::class)->named('app'),
			Route::get('/{screen:(?!api/|assets/)[A-Za-z0-9_./-]+}', ShellController::class)->named('screen')
		], name: 'admin.');

		return [...$api, ...$app];
	}
}
