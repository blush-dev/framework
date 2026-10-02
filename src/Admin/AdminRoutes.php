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
 * The admin's routes, only while the admin is on, all `exact()`: the
 * trailing-slash setting never redirects them, so each request is one
 * round trip and the admin's addresses stay as it writes them.
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
 *   - `PATCH profile`: changes the account's own name (D-322).
 *   - `POST password`: changes the account's own password.
 *   - `POST set-password`: sets a password with a password link, and
 *     signs in (D-312; no account needed).
 *   - `GET  dashboard`: the site, content counts, and the actions the
 *     account may run.
 *   - `POST actions/{action}`: runs an action.
 *   - `GET  types`: the site's content types, and `GET types/{name}` one;
 *     `POST types`, `PATCH` and `DELETE types/{name}`, and `POST
 *     types/refresh` edit the ones in `user/data/types`, and change
 *     code collections and taxonomies there; `POST types/{name}/reset`
 *     puts one back as the code has it (`TypeEditController`, D-349).
 *   - `GET  fields/types`: the field types definitions can use, with
 *     their controls (`FieldTypesController`, D-337).
 *   - `GET  fields/sets`: the site's field sets, and `GET
 *     fields/sets/{name}` one; `POST fields/sets`, and `PATCH` and
 *     `DELETE fields/sets/{name}` edit the ones in `user/data/fields`
 *     (`FieldSetEditController`, D-337).
 *   - `GET  components`: the components the editor's inserter offers.
 *   - `GET  icons`: the icons the editor's icon picker offers.
 *   - `GET  media`: the media files an entry can use, a page at a time,
 *     and `GET media/{path}` one library file; `POST media` uploads one,
 *     and `PATCH media/{path}` changes a library file's alt text and
 *     caption.
 *   - `GET  references/{type}`: what a reference field to a type can
 *     point at, for the editor's picker.
 *   - `GET  entries`: the entries the account may edit, a page at a time.
 *   - `POST entries`, and `GET`, `PATCH`, and `DELETE entries/{id}`:
 *     the editing API (`EntryController`), `POST entries/bulk` (D-301),
 *     `POST entries/{id}/duplicate`, `GET entries/new` (a new entry, not
 *     yet written, D-336),
 *     and `GET content/{type}/{key}`, an entry by its handle (D-253).
 *   - `GET  trash` and `GET trash/{id}`, and `POST trash/restore`,
 *     `trash/delete`, and `trash/empty`: the trash (`TrashController`).
 *   - `GET  health`: the content's lint problems.
 *   - `GET  calendar`: a month of dated entries (`CalendarController`,
 *     D-368).
 *   - `GET  roles` and `GET accounts`: the site's roles and accounts
 *     (`PeopleController`); `GET profiles`, `GET profiles/{slug}`, and `POST` and
 *     `DELETE` the pages written for its archives (`ProfilesController`,
 *     D-353); `POST accounts`, `PATCH` and `DELETE
 *     accounts/{username}`, and `POST accounts/{username}/link` change
 *     accounts (`AccountEditController`), and `POST roles`, and `PATCH`
 *     and `DELETE roles/{name}` change roles (`RoleEditController`).
 *   - `GET  appearance`: the installed themes, to show
 *     (`AppearanceController`).
 *   - `GET  extensions`: the installed extensions and what each adds,
 *     to show (`ExtensionsController`).
 *   - `GET  settings/{screen}`: a Settings screen's settings
 *     (`SettingsController`, D-325); `PATCH settings` saves the ones
 *     the admin can change in `user/data/settings.json`, and `POST
 *     settings/refresh` compiles and reindexes after
 *     (`SettingsEditController`, D-324).
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
			Route::patch('/profile', ProfileController::class)->named('profile')->middleware(Authenticate::class),
			Route::post('/password', PasswordController::class)->named('password')->middleware(Authenticate::class),
			Route::post('/set-password', SetPasswordController::class)->named('set-password'),
			Route::get('/dashboard', DashboardController::class)->named('dashboard')->middleware(Authenticate::class),
			Route::get('/counts', CountsController::class)->named('counts')->middleware(Authenticate::class),
			Route::post('/actions/{action:[a-z0-9][a-z0-9-]*}', ActionController::class)->named('action')->middleware(Authenticate::class),
			Route::get('/types', TypesController::class)->named('types')->middleware(Authenticate::class),
			Route::post('/types', [TypeEditController::class, 'create'])->named('type.create')->middleware(Authenticate::class),
			Route::post('/types/refresh', [TypeEditController::class, 'refresh'])->named('types.refresh')->middleware(Authenticate::class),
			Route::get('/types/{name:[a-z0-9_-]+}', [TypesController::class, 'show'])->named('type')->middleware(Authenticate::class),
			Route::patch('/types/{name:[a-z0-9_-]+}', [TypeEditController::class, 'update'])->named('type.update')->middleware(Authenticate::class),
			Route::delete('/types/{name:[a-z0-9_-]+}', [TypeEditController::class, 'delete'])->named('type.delete')->middleware(Authenticate::class),
			Route::post('/types/{name:[a-z0-9_-]+}/reset', [TypeEditController::class, 'reset'])->named('type.reset')->middleware(Authenticate::class),
			Route::get('/components', ComponentsController::class)->named('components')->middleware(Authenticate::class),
			Route::get('/fields/types', FieldTypesController::class)->named('fields.types')->middleware(Authenticate::class),
			Route::get('/fields/sets', FieldSetsController::class)->named('fields.sets')->middleware(Authenticate::class),
			Route::post('/fields/sets', [FieldSetEditController::class, 'create'])->named('fields.set.create')->middleware(Authenticate::class),
			Route::get('/fields/sets/{name:[a-z0-9_-]+}', [FieldSetsController::class, 'show'])->named('fields.set')->middleware(Authenticate::class),
			Route::patch('/fields/sets/{name:[a-z0-9_-]+}', [FieldSetEditController::class, 'update'])->named('fields.set.update')->middleware(Authenticate::class),
			Route::delete('/fields/sets/{name:[a-z0-9_-]+}', [FieldSetEditController::class, 'delete'])->named('fields.set.delete')->middleware(Authenticate::class),
			Route::get('/icons', IconsController::class)->named('icons')->middleware(Authenticate::class),
			Route::get('/media', MediaListController::class)->named('media')->middleware(Authenticate::class),
			Route::post('/media', MediaUploadController::class)->named('media.upload')->middleware(Authenticate::class),
			Route::get('/media/{path:.+}', [MediaListController::class, 'show'])->named('media.file')->middleware(Authenticate::class),
			Route::patch('/media/{path:.+}', [MediaListController::class, 'update'])->named('media.update')->middleware(Authenticate::class),
			Route::get('/references/{type:[a-z0-9_-]+}', ReferencesController::class)->named('references')->middleware(Authenticate::class),
			Route::get('/entries', EntriesController::class)->named('entries')->middleware(Authenticate::class),
			Route::post('/entries', [EntryController::class, 'create'])->named('entry.create')->middleware(Authenticate::class),
			Route::post('/entries/bulk', [EntryController::class, 'bulk'])->named('entry.bulk')->middleware(Authenticate::class),
			Route::get('/entries/new', [EntryController::class, 'blank'])->named('entry.new')->middleware(Authenticate::class),
			Route::post('/entries/{id:.+}/duplicate', [EntryController::class, 'duplicate'])->named('entry.duplicate')->middleware(Authenticate::class),
			Route::get('/entries/{id:.+}', [EntryController::class, 'show'])->named('entry')->middleware(Authenticate::class),
			Route::get('/content/{type:[a-z0-9_-]+}/{key:.+}', [EntryController::class, 'named'])->named('entry.named')->middleware(Authenticate::class),
			Route::patch('/entries/{id:.+}', [EntryController::class, 'update'])->named('entry.update')->middleware(Authenticate::class),
			Route::delete('/entries/{id:.+}', [EntryController::class, 'delete'])->named('entry.delete')->middleware(Authenticate::class),
			Route::get('/trash', [TrashController::class, 'index'])->named('trash')->middleware(Authenticate::class),
			Route::get('/trash/{id:.+}', [TrashController::class, 'show'])->named('trash.entry')->middleware(Authenticate::class),
			Route::post('/trash/restore', [TrashController::class, 'restore'])->named('trash.restore')->middleware(Authenticate::class),
			Route::post('/trash/delete', [TrashController::class, 'delete'])->named('trash.delete')->middleware(Authenticate::class),
			Route::post('/trash/empty', [TrashController::class, 'empty'])->named('trash.empty')->middleware(Authenticate::class),
			Route::get('/health', HealthController::class)->named('health')->middleware(Authenticate::class),
			Route::get('/calendar', CalendarController::class)->named('calendar')->middleware(Authenticate::class),
			Route::get('/roles', [PeopleController::class, 'roles'])->named('roles')->middleware(Authenticate::class),
			Route::post('/roles', [RoleEditController::class, 'create'])->named('role.create')->middleware(Authenticate::class),
			Route::patch('/roles/{name:[a-z][a-z0-9_-]*}', [RoleEditController::class, 'update'])->named('role.update')->middleware(Authenticate::class),
			Route::delete('/roles/{name:[a-z][a-z0-9_-]*}', [RoleEditController::class, 'delete'])->named('role.delete')->middleware(Authenticate::class),
			Route::get('/accounts', [PeopleController::class, 'accounts'])->named('accounts')->middleware(Authenticate::class),
			Route::get('/profiles', [ProfilesController::class, 'index'])->named('profiles')->middleware(Authenticate::class),
			Route::get('/profiles/{slug:[^/]+}', [ProfilesController::class, 'show'])->named('profile.show')->middleware(Authenticate::class),
			Route::post('/profiles/{slug:[^/]+}/pages', [ProfilesController::class, 'write'])->named('profile.page.write')->middleware(Authenticate::class),
			Route::delete('/profiles/{slug:[^/]+}/pages/{type:[a-z0-9_]+}/{field:[a-z0-9_]+}', [ProfilesController::class, 'remove'])->named('profile.page.remove')->middleware(Authenticate::class),
			Route::post('/accounts', [AccountEditController::class, 'create'])->named('account.create')->middleware(Authenticate::class),
			Route::post('/accounts/{username:[a-z0-9][a-z0-9._-]*}/link', [AccountEditController::class, 'link'])->named('account.link')->middleware(Authenticate::class),
			Route::patch('/accounts/{username:[a-z0-9][a-z0-9._-]*}', [AccountEditController::class, 'update'])->named('account.update')->middleware(Authenticate::class),
			Route::delete('/accounts/{username:[a-z0-9][a-z0-9._-]*}', [AccountEditController::class, 'delete'])->named('account.delete')->middleware(Authenticate::class),
			Route::get('/appearance', AppearanceController::class)->named('appearance')->middleware(Authenticate::class),
			Route::get('/extensions', ExtensionsController::class)->named('extensions')->middleware(Authenticate::class),
			Route::patch('/settings', [SettingsEditController::class, 'update'])->named('settings.update')->middleware(Authenticate::class),
			Route::post('/settings/refresh', [SettingsEditController::class, 'refresh'])->named('settings.refresh')->middleware(Authenticate::class),
			Route::get('/settings/{screen:[a-z]+}', SettingsController::class)->named('settings')->middleware(Authenticate::class),
			Route::post('/previews', PreviewLinkController::class)->named('preview')->middleware(Authenticate::class)
		], name: 'admin.api.', middleware: [StartSession::class, VerifyCsrf::class], exact: true);

		$app = Route::group($this->config->path, [
			Route::get('/assets/{file:.+}', AssetController::class)->named('asset'),
			Route::get('/', ShellController::class)->named('app'),
			Route::get('/{screen:(?!api/|assets/)[A-Za-z0-9_./-]+}', ShellController::class)->named('screen')
		], name: 'admin.', exact: true);

		return [...$api, ...$app];
	}
}
