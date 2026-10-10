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
use Blush\Admin\Redirects\RedirectsController;
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
 *   - `GET  dashboard`: the site, and the entries waiting on the account
 *     (D-538).
 *   - `GET  actions`: the actions the account may run, by source, and
 *     `POST actions/{action}` runs one (D-540).
 *   - `GET  jobs`: background jobs, the schedule, and the runners,
 *     `GET jobs/{id}` one job, `POST jobs/{id}/run` its next chunk,
 *     `POST jobs/{id}/retry`, `DELETE jobs/{id}`, and `POST
 *     jobs/schedule/{job}` to run a scheduled task now (D-621).
 *   - `GET  logs`: the end of the site's log, and `GET logs/download`
 *     all of it (D-540, D-541).
 *   - `GET  types`: the site's content types, and `GET types/{name}` one;
 *     `POST types`, `PATCH` and `DELETE types/{name}`, and `POST
 *     types/refresh` edit the ones in `user/data/types`, and change
 *     code collections there; `POST types/{name}/reset`
 *     puts one back as the code has it (`TypeEditController`, D-349).
 *   - `GET  redirects`: the site's redirects, a page at a time, with
 *     their problems; `POST redirects` adds or changes one, and `POST
 *     redirects/check`, `delete`, `status`, and `restore` check, delete,
 *     retype, and put back rows (`Redirects\RedirectsController`, D-686).
 *   - `GET  menus`: the site's menus and the active theme's locations;
 *     `GET menus/{name}` one menu's items, `POST menus` writes one (and
 *     renames it), `DELETE menus/{name}` removes one, `PUT
 *     menu-locations/{location}` assigns a location, and `GET
 *     menu-links` searches what an item can link to (`MenusController`).
 *   - `GET  relations`: the site's relation definitions
 *     (`RelationsController`, D-593); `POST relations`, and `PATCH` and
 *     `DELETE relations/{name}` edit the ones in `user/data/relations`
 *     (`TypeEditController`).
 *   - `GET  fields/types`: the field types definitions can use, with
 *     their controls (`FieldTypesController`, D-337).
 *   - `GET  fields/sets`: the site's field sets, and `GET
 *     fields/sets/{name}` one; `POST fields/sets`, and `PATCH` and
 *     `DELETE fields/sets/{name}` edit the ones in `user/data/fields`
 *     (`FieldSetEditController`, D-337).
 *   - `GET  directives`: the directives the editor's inserter offers.
 *   - `GET  icons`: the icons the editor's icon picker offers.
 *   - `GET  media`: the media files an entry can use, a page at a time,
 *     and `GET media/{path}` one library file (`GET media-artwork/{path}`
 *     the picture a sound or video carries, D-551; `PUT` links a library
 *     image as its artwork, or adds the carried one, and `DELETE` takes
 *     it off, D-581); `POST media` uploads one, and `PATCH media/{path}`
 *     changes a library file's alt text and caption.
 *   - `GET  references/{type}`: what a reference field to a type can
 *     point at, for the editor's picker.
 *   - `GET  entries`: the entries the account may edit, a page at a
 *     time, or with `status=trash`, those in the trash it may delete.
 *   - `POST entries`, and `GET`, `PATCH`, and `DELETE entries/{id}`:
 *     the editing API (`EntryController`), `POST entries/bulk` (D-301),
 *     `POST entries/{id}/duplicate`, `GET entries/{id}/referrers` (what
 *     links to it, D-598), `GET entries/new` (a new entry, not
 *     yet written, D-336), each entry named by its id (D-481, D-483);
 *     and the trash (D-484): `POST entries/{id}/restore` and `POST
 *     entries/empty-trash`.
 *   - `GET  health/site`: Site Health's last report (D-543, D-545), and
 *     `POST health/site` checks again; with `site.health`, as
 *     everything under `health`.
 *   - `GET  health`: the content's lint problems as last checked, and
 *     `POST health` checks them again (D-546), and `POST health/ids`
 *     and `POST health/ids/keep` to fix ids (D-477, D-478), and `POST
 *     health/media-ids` and `POST health/media-ids/keep` for media's
 *     (D-487), `POST health/media-sizes` to record images' sizes
 *     (D-488), `POST health/filenames` to rename a type's files to its
 *     pattern (D-512), `POST health/folders` to move collections'
 *     entries into their folders (D-514, D-629), and `POST health/terms` to write
 *     the terms and profiles entries name with no file (D-584), `POST
 *     health/refs` to file links between entries with their ids
 *     (D-596), `POST health/ignore` and `POST health/unignore` (D-613),
 *     and `POST health/taxonomies` to migrate data types still written as
 *     taxonomies (D-591), and `POST health/type-folders` to move data
 *     types that name their folder (D-683).
 *   - `GET  roles` and `GET accounts`: the site's roles and accounts
 *     (`PeopleController`); `GET profiles`, `GET` and `PATCH profiles/{slug}`
 *     (the latter locks it against linking, D-605), and `POST` and
 *     `DELETE` the pages written for its archives (`ProfilesController`,
 *     D-353); `POST accounts`, `PATCH` and `DELETE
 *     accounts/{username}`, and `POST accounts/{username}/link` change
 *     accounts (`AccountEditController`), and `POST roles`, and `PATCH`
 *     and `DELETE roles/{name}` change roles (`RoleEditController`).
 *   - `GET  themes`: the installed themes (`ThemesController`);
 *     `DELETE themes/{vendor}/{name}` deletes one from `extensions/`
 *     (`ThemeEditController`, D-381). `PATCH settings` activates one
 *     (`theme.active`).
 *   - `GET  plugins`: the installed plugins, each checked against
 *     the site (`PluginsController`); `PUT plugins/{vendor}/{name}`
 *     turns one on or off, and `DELETE plugins/{vendor}/{name}` deletes
 *     one from `extensions/` (`PluginEditController`, D-385).
 *   - `GET  icon-packs`: the installed icon packs and the core set,
 *     `GET icon-packs/{vendor}/{name}` and `GET icon-packs/core` one
 *     with every icon (`IconPacksController`); `PUT
 *     icon-packs/{vendor}/{name}` turns one on or off, and `DELETE
 *     icon-packs/{vendor}/{name}` deletes one from `extensions/`
 *     (`IconPackEditController`, D-385).
 *   - `GET  settings/{screen}`: a Settings screen's settings
 *     (`SettingsController`, D-325); `PATCH settings` saves the ones
 *     the admin can change in the saved settings (`user/data/settings/`), and `POST
 *     settings/refresh` compiles and reindexes after
 *     (`SettingsEditController`, D-324). `GET settings/date-format`
 *     shows how a date or time format reads now (D-445).
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
			Route::get('/actions', [ActionController::class, 'index'])->named('actions')->middleware(Authenticate::class),
			Route::post('/actions/{action:[a-z0-9][a-z0-9-]*}', ActionController::class)->named('action')->middleware(Authenticate::class),
			Route::get('/jobs', [JobController::class, 'index'])->named('jobs')->middleware(Authenticate::class),
			Route::post('/jobs/schedule/{job:[a-z0-9][a-z0-9-]*/[a-z0-9][a-z0-9-]*}', [JobController::class, 'schedule'])->named('jobs.schedule')->middleware(Authenticate::class),
			Route::get('/jobs/{id:[0-9a-f-]{36}}', [JobController::class, 'show'])->named('job')->middleware(Authenticate::class),
			Route::post('/jobs/{id:[0-9a-f-]{36}}/run', [JobController::class, 'run'])->named('job.run')->middleware(Authenticate::class),
			Route::post('/jobs/{id:[0-9a-f-]{36}}/retry', [JobController::class, 'retry'])->named('job.retry')->middleware(Authenticate::class),
			Route::delete('/jobs/{id:[0-9a-f-]{36}}', [JobController::class, 'delete'])->named('job.delete')->middleware(Authenticate::class),
			Route::get('/logs', [LogController::class, 'show'])->named('logs')->middleware(Authenticate::class),
			Route::get('/logs/download', [LogController::class, 'download'])->named('logs.download')->middleware(Authenticate::class),
			Route::get('/types', TypesController::class)->named('types')->middleware(Authenticate::class),
			Route::post('/types', [TypeEditController::class, 'create'])->named('type.create')->middleware(Authenticate::class),
			Route::post('/types/refresh', [TypeEditController::class, 'refresh'])->named('types.refresh')->middleware(Authenticate::class),
			Route::get('/types/{name:[a-z0-9_-]+}', [TypesController::class, 'show'])->named('type')->middleware(Authenticate::class),
			Route::patch('/types/{name:[a-z0-9_-]+}', [TypeEditController::class, 'update'])->named('type.update')->middleware(Authenticate::class),
			Route::delete('/types/{name:[a-z0-9_-]+}', [TypeEditController::class, 'delete'])->named('type.delete')->middleware(Authenticate::class),
			Route::post('/types/{name:[a-z0-9_-]+}/reset', [TypeEditController::class, 'reset'])->named('type.reset')->middleware(Authenticate::class),
			Route::get('/redirects', [RedirectsController::class, 'index'])->named('redirects')->middleware(Authenticate::class),
			Route::post('/redirects', [RedirectsController::class, 'save'])->named('redirect.save')->middleware(Authenticate::class),
			Route::post('/redirects/check', [RedirectsController::class, 'check'])->named('redirects.check')->middleware(Authenticate::class),
			Route::post('/redirects/delete', [RedirectsController::class, 'delete'])->named('redirects.delete')->middleware(Authenticate::class),
			Route::post('/redirects/status', [RedirectsController::class, 'status'])->named('redirects.status')->middleware(Authenticate::class),
			Route::post('/redirects/restore', [RedirectsController::class, 'restore'])->named('redirects.restore')->middleware(Authenticate::class),
			Route::get('/menus', [MenusController::class, 'index'])->named('menus')->middleware(Authenticate::class),
			Route::post('/menus', [MenusController::class, 'save'])->named('menu.save')->middleware(Authenticate::class),
			Route::get('/menus/{name:[a-z0-9][a-z0-9_-]*}', [MenusController::class, 'show'])->named('menu')->middleware(Authenticate::class),
			Route::delete('/menus/{name:[a-z0-9][a-z0-9_-]*}', [MenusController::class, 'delete'])->named('menu.delete')->middleware(Authenticate::class),
			Route::put('/menu-locations/{location:[A-Za-z0-9_-]+}', [MenusController::class, 'assign'])->named('menu.assign')->middleware(Authenticate::class),
			Route::get('/menu-links', [MenusController::class, 'links'])->named('menu.links')->middleware(Authenticate::class),
			Route::get('/relations', RelationsController::class)->named('relations')->middleware(Authenticate::class),
			Route::post('/relations', [TypeEditController::class, 'createRelation'])->named('relation.create')->middleware(Authenticate::class),
			Route::post('/relations/{name:[a-z0-9_]+}/check', [TypeEditController::class, 'checkRelation'])->named('relation.check')->middleware(Authenticate::class),
			Route::get('/relations/{name:[a-z0-9_]+}/uses', [TypeEditController::class, 'relationUses'])->named('relation.uses')->middleware(Authenticate::class),
			Route::patch('/relations/{name:[a-z0-9_]+}', [TypeEditController::class, 'updateRelation'])->named('relation.update')->middleware(Authenticate::class),
			Route::delete('/relations/{name:[a-z0-9_]+}', [TypeEditController::class, 'deleteRelation'])->named('relation.delete')->middleware(Authenticate::class),
			Route::get('/directives', DirectivesController::class)->named('directives')->middleware(Authenticate::class),
			Route::get('/fields/types', FieldTypesController::class)->named('fields.types')->middleware(Authenticate::class),
			Route::get('/fields/sets', FieldSetsController::class)->named('fields.sets')->middleware(Authenticate::class),
			Route::post('/fields/sets', [FieldSetEditController::class, 'create'])->named('fields.set.create')->middleware(Authenticate::class),
			Route::get('/fields/sets/{name:[a-z0-9_-]+}', [FieldSetsController::class, 'show'])->named('fields.set')->middleware(Authenticate::class),
			Route::patch('/fields/sets/{name:[a-z0-9_-]+}', [FieldSetEditController::class, 'update'])->named('fields.set.update')->middleware(Authenticate::class),
			Route::delete('/fields/sets/{name:[a-z0-9_-]+}', [FieldSetEditController::class, 'delete'])->named('fields.set.delete')->middleware(Authenticate::class),
			Route::get('/icons', IconsController::class)->named('icons')->middleware(Authenticate::class),
			Route::get('/media', MediaListController::class)->named('media')->middleware(Authenticate::class),
			Route::post('/media', MediaUploadController::class)->named('media.upload')->middleware(Authenticate::class),
			Route::get('/media-artwork/{path:.+}', [MediaListController::class, 'artwork'])->named('media.artwork')->middleware(Authenticate::class),
			Route::put('/media-artwork/{path:.+}', [MediaListController::class, 'setArtwork'])->named('media.artwork.set')->middleware(Authenticate::class),
			Route::delete('/media-artwork/{path:.+}', [MediaListController::class, 'removeArtwork'])->named('media.artwork.remove')->middleware(Authenticate::class),
			Route::get('/media/{path:.+}', [MediaListController::class, 'show'])->named('media.file')->middleware(Authenticate::class),
			Route::patch('/media/{path:.+}', [MediaListController::class, 'update'])->named('media.update')->middleware(Authenticate::class),
			Route::delete('/media/{path:.+}', [MediaListController::class, 'delete'])->named('media.delete')->middleware(Authenticate::class),
			Route::get('/references/{type:[a-z0-9_-]+}', ReferencesController::class)->named('references')->middleware(Authenticate::class),
			Route::get('/entries', EntriesController::class)->named('entries')->middleware(Authenticate::class),
			Route::post('/entries', [EntryController::class, 'create'])->named('entry.create')->middleware(Authenticate::class),
			Route::post('/entries/referrers', [EntryController::class, 'manyReferrers'])->named('entry.referrers.many')->middleware(Authenticate::class),
			Route::post('/entries/bulk', [EntryController::class, 'bulk'])->named('entry.bulk')->middleware(Authenticate::class),
			Route::get('/entries/new', [EntryController::class, 'blank'])->named('entry.new')->middleware(Authenticate::class),
			Route::post('/entries/empty-trash', [EntryController::class, 'emptyTrash'])->named('entry.empty-trash')->middleware(Authenticate::class),
			Route::post('/entries/{id:[0-9a-fA-F-]{36}}/restore', [EntryController::class, 'restore'])->named('entry.restore')->middleware(Authenticate::class),
			Route::post('/entries/{id:[0-9a-fA-F-]{36}}/duplicate', [EntryController::class, 'duplicate'])->named('entry.duplicate')->middleware(Authenticate::class),
			Route::get('/entries/{id:[0-9a-fA-F-]{36}}/referrers', [EntryController::class, 'referrers'])->named('entry.referrers')->middleware(Authenticate::class),
			Route::get('/entries/{id:[0-9a-fA-F-]{36}}', [EntryController::class, 'show'])->named('entry')->middleware(Authenticate::class),
			Route::patch('/entries/{id:[0-9a-fA-F-]{36}}', [EntryController::class, 'update'])->named('entry.update')->middleware(Authenticate::class),
			Route::delete('/entries/{id:[0-9a-fA-F-]{36}}', [EntryController::class, 'delete'])->named('entry.delete')->middleware(Authenticate::class),
			Route::get('/health', HealthController::class)->named('health')->middleware(Authenticate::class),
			Route::post('/health', [HealthController::class, 'check'])->named('health.check')->middleware(Authenticate::class),
			Route::get('/health/site', [SiteHealthController::class, 'show'])->named('health.site')->middleware(Authenticate::class),
			Route::post('/health/site', [SiteHealthController::class, 'run'])->named('health.site.run')->middleware(Authenticate::class),
			Route::post('/health/ids', [HealthController::class, 'assign'])->named('health.ids')->middleware(Authenticate::class),
			Route::post('/health/ids/keep', [HealthController::class, 'keep'])->named('health.ids.keep')->middleware(Authenticate::class),
			Route::post('/health/media-ids', [HealthController::class, 'assignMedia'])->named('health.media-ids')->middleware(Authenticate::class),
			Route::post('/health/media-ids/keep', [HealthController::class, 'keepMedia'])->named('health.media-ids.keep')->middleware(Authenticate::class),
			Route::post('/health/media-sizes', [HealthController::class, 'recordSizes'])->named('health.media-sizes')->middleware(Authenticate::class),
			Route::post('/health/filenames', [HealthController::class, 'renameFiles'])->named('health.filenames')->middleware(Authenticate::class),
			Route::post('/health/folders', [HealthController::class, 'folders'])->named('health.folders')->middleware(Authenticate::class),
			Route::post('/health/terms', [HealthController::class, 'createTerms'])->named('health.terms')->middleware(Authenticate::class),
			Route::post('/health/parents', [HealthController::class, 'createParents'])->named('health.parents')->middleware(Authenticate::class),
			Route::post('/health/refs', [HealthController::class, 'fileRefs'])->named('health.refs')->middleware(Authenticate::class),
			Route::post('/health/taxonomies', [HealthController::class, 'migrateTaxonomies'])->named('health.taxonomies')->middleware(Authenticate::class),
			Route::post('/health/type-folders', [HealthController::class, 'moveTypeFolders'])->named('health.type-folders')->middleware(Authenticate::class),
			Route::post('/health/ignore', [HealthController::class, 'ignore'])->named('health.ignore')->middleware(Authenticate::class),
			Route::post('/health/unignore', [HealthController::class, 'unignore'])->named('health.unignore')->middleware(Authenticate::class),
			Route::get('/roles', [PeopleController::class, 'roles'])->named('roles')->middleware(Authenticate::class),
			Route::post('/roles', [RoleEditController::class, 'create'])->named('role.create')->middleware(Authenticate::class),
			Route::patch('/roles/{name:[a-z][a-z0-9_-]*}', [RoleEditController::class, 'update'])->named('role.update')->middleware(Authenticate::class),
			Route::delete('/roles/{name:[a-z][a-z0-9_-]*}', [RoleEditController::class, 'delete'])->named('role.delete')->middleware(Authenticate::class),
			Route::get('/accounts', [PeopleController::class, 'accounts'])->named('accounts')->middleware(Authenticate::class),
			Route::get('/profiles', [ProfilesController::class, 'index'])->named('profiles')->middleware(Authenticate::class),
			Route::get('/profiles/{slug:[^/]+}', [ProfilesController::class, 'show'])->named('profile.show')->middleware(Authenticate::class),
			Route::patch('/profiles/{slug:[^/]+}', [ProfilesController::class, 'lock'])->named('profile.lock')->middleware(Authenticate::class),
			Route::post('/profiles/{slug:[^/]+}/pages', [ProfilesController::class, 'write'])->named('profile.page.write')->middleware(Authenticate::class),
			Route::delete('/profiles/{slug:[^/]+}/pages/{type:[a-z0-9_]+}/{relation:[a-z0-9_]+}', [ProfilesController::class, 'remove'])->named('profile.page.remove')->middleware(Authenticate::class),
			Route::post('/accounts', [AccountEditController::class, 'create'])->named('account.create')->middleware(Authenticate::class),
			Route::post('/accounts/{username:[a-z0-9][a-z0-9._-]*}/link', [AccountEditController::class, 'link'])->named('account.link')->middleware(Authenticate::class),
			Route::patch('/accounts/{username:[a-z0-9][a-z0-9._-]*}', [AccountEditController::class, 'update'])->named('account.update')->middleware(Authenticate::class),
			Route::delete('/accounts/{username:[a-z0-9][a-z0-9._-]*}', [AccountEditController::class, 'delete'])->named('account.delete')->middleware(Authenticate::class),
			Route::get('/themes', ThemesController::class)->named('themes')->middleware(Authenticate::class),
			Route::post('/themes', [ExtensionInstallController::class, 'theme'])->named('theme.install')->middleware(Authenticate::class),
			Route::post('/themes/{vendor:[a-z0-9][a-z0-9._-]*}/{name:[a-z0-9][a-z0-9._-]*}/rollback', [ExtensionBackupController::class, 'rollbackTheme'])->named('theme.rollback')->middleware(Authenticate::class),
			Route::delete('/themes/{vendor:[a-z0-9][a-z0-9._-]*}/{name:[a-z0-9][a-z0-9._-]*}/backup', [ExtensionBackupController::class, 'discardTheme'])->named('theme.backup.discard')->middleware(Authenticate::class),
			Route::delete('/themes/{vendor:[a-z0-9][a-z0-9._-]*}/{name:[a-z0-9][a-z0-9._-]*}', [ThemeEditController::class, 'delete'])->named('theme.delete')->middleware(Authenticate::class),
			Route::get('/plugins', PluginsController::class)->named('plugins')->middleware(Authenticate::class),
			Route::post('/plugins', [ExtensionInstallController::class, 'plugin'])->named('plugin.install')->middleware(Authenticate::class),
			Route::put('/plugins/{vendor:[a-z0-9][a-z0-9._-]*}/{name:[a-z0-9][a-z0-9._-]*}', [PluginEditController::class, 'toggle'])->named('plugin.toggle')->middleware(Authenticate::class),
			Route::post('/plugins/{vendor:[a-z0-9][a-z0-9._-]*}/{name:[a-z0-9][a-z0-9._-]*}/rollback', [ExtensionBackupController::class, 'rollbackPlugin'])->named('plugin.rollback')->middleware(Authenticate::class),
			Route::delete('/plugins/{vendor:[a-z0-9][a-z0-9._-]*}/{name:[a-z0-9][a-z0-9._-]*}/backup', [ExtensionBackupController::class, 'discardPlugin'])->named('plugin.backup.discard')->middleware(Authenticate::class),
			Route::delete('/plugins/{vendor:[a-z0-9][a-z0-9._-]*}/{name:[a-z0-9][a-z0-9._-]*}', [PluginEditController::class, 'delete'])->named('plugin.delete')->middleware(Authenticate::class),
			Route::get('/icon-packs', IconPacksController::class)->named('icon-packs')->middleware(Authenticate::class),
			Route::get('/icon-packs/core', [IconPacksController::class, 'core'])->named('icon-packs.core')->middleware(Authenticate::class),
			Route::get('/icon-packs/{vendor:[a-z0-9][a-z0-9._-]*}/{name:[a-z0-9][a-z0-9._-]*}', [IconPacksController::class, 'show'])->named('icon-pack')->middleware(Authenticate::class),
			Route::post('/icon-packs', [ExtensionInstallController::class, 'iconPack'])->named('icon-pack.install')->middleware(Authenticate::class),
			Route::put('/icon-packs/{vendor:[a-z0-9][a-z0-9._-]*}/{name:[a-z0-9][a-z0-9._-]*}', [IconPackEditController::class, 'toggle'])->named('icon-pack.toggle')->middleware(Authenticate::class),
			Route::post('/icon-packs/{vendor:[a-z0-9][a-z0-9._-]*}/{name:[a-z0-9][a-z0-9._-]*}/rollback', [ExtensionBackupController::class, 'rollbackIconPack'])->named('icon-pack.rollback')->middleware(Authenticate::class),
			Route::delete('/icon-packs/{vendor:[a-z0-9][a-z0-9._-]*}/{name:[a-z0-9][a-z0-9._-]*}/backup', [ExtensionBackupController::class, 'discardIconPack'])->named('icon-pack.backup.discard')->middleware(Authenticate::class),
			Route::delete('/icon-packs/{vendor:[a-z0-9][a-z0-9._-]*}/{name:[a-z0-9][a-z0-9._-]*}', [IconPackEditController::class, 'delete'])->named('icon-pack.delete')->middleware(Authenticate::class),
			Route::patch('/settings', [SettingsEditController::class, 'update'])->named('settings.update')->middleware(Authenticate::class),
			Route::post('/settings/refresh', [SettingsEditController::class, 'refresh'])->named('settings.refresh')->middleware(Authenticate::class),
			Route::get('/settings/date-format', [SettingsController::class, 'format'])->named('settings.date-format')->middleware(Authenticate::class),
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
