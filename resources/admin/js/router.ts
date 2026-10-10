/**
 * The admin's screens. Every screen but sign-in needs an account, and some
 * a capability (`meta.capability`, any of `meta.anyCapability`, or a
 * content action on a type, `meta.contentAction`); the server answers the same page for
 * all of them (`ShellController`) and checks every API request itself.
 * Each belongs to one of the section rail's areas
 * (`meta.area`: `home`, `content`, `people`, `config`, or `extend`); the editor fills the
 * work area edge to edge (`meta.bleed`), and the Settings screens drop the
 * work area's widest measure (`meta.wide`, D-404).
 */

import { createRouter, createWebHistory, START_LOCATION, type RouteLocationNormalized } from 'vue-router';
import { watch } from 'vue';
import { config } from './config';
import { lastVisits, screenCrumb, screenTitle, screenTrail } from './screen';
import { can, canAnyType, canType, loadSession, MEDIA_CAPABILITIES, session, type ContentAction } from './session';
import DashboardView from './views/DashboardView.vue';
import NotFoundView from './views/NotFoundView.vue';
import SignInView from './views/SignInView.vue';

// Every other screen loads when it's opened (D-687, D-689): a new screen
// costs the first one nothing. The dashboard, sign-in, and not-found
// screens are what a page most often opens on, and small, so they're
// in `admin.js`.
const AccountView = () => import('./views/AccountView.vue');
const AccountsView = () => import('./views/AccountsView.vue');
const EditorView = () => import('./views/EditorView.vue');
const EntriesView = () => import('./views/EntriesView.vue');
const FieldSetView = () => import('./views/FieldSetView.vue');
const FieldSetsView = () => import('./views/FieldSetsView.vue');
const HealthView = () => import('./views/HealthView.vue');
const IconPackView = () => import('./views/IconPackView.vue');
const IconPacksView = () => import('./views/IconPacksView.vue');
const MediaFileView = () => import('./views/MediaFileView.vue');
const MenusView = () => import('./views/MenusView.vue');
const MenuView = () => import('./views/MenuView.vue');
const MediaView = () => import('./views/MediaView.vue');
const NewAccountView = () => import('./views/NewAccountView.vue');
const NewFieldSetView = () => import('./views/NewFieldSetView.vue');
const NewRoleView = () => import('./views/NewRoleView.vue');
const NewTypeView = () => import('./views/NewTypeView.vue');
const PluginView = () => import('./views/PluginView.vue');
const PluginsView = () => import('./views/PluginsView.vue');
const ProfileDetailView = () => import('./views/ProfileDetailView.vue');
const RedirectsView = () => import('./views/RedirectsView.vue');
const RelationView = () => import('./views/RelationView.vue');
const RelationsView = () => import('./views/RelationsView.vue');
const RoleView = () => import('./views/RoleView.vue');
const RolesView = () => import('./views/RolesView.vue');
const SetPasswordView = () => import('./views/SetPasswordView.vue');
const SettingsView = () => import('./views/SettingsView.vue');
const SiteHealthView = () => import('./views/SiteHealthView.vue');
const ThemeView = () => import('./views/ThemeView.vue');
const ThemesView = () => import('./views/ThemesView.vue');
const ToolsView = () => import('./views/ToolsView.vue');
const TrashedView = () => import('./views/TrashedView.vue');
const TypeView = () => import('./views/TypeView.vue');
const TypesView = () => import('./views/TypesView.vue');

export const router = createRouter({
	history: createWebHistory(config.base),
	routes: [
		{ path: '/', name: 'dashboard', component: DashboardView, meta: { title: 'Dashboard', area: 'home', narrow: true } },
		// Each type has its own list; there's no list of every type (D-240).
		{ path: '/entries', redirect: { name: 'dashboard' } },
		{ path: '/content/:type', name: 'type', component: EntriesView, meta: { title: 'Entries', contentAction: 'edit', section: 'entries', area: 'content' } },
		// An entry is edited at its handle, its type and key (D-253); a
		// page's key spans segments.
		// An entry, by its type and id (D-483).
		{ path: '/content/:type/:id([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})', name: 'entry', component: EditorView, meta: { title: 'Edit Entry', contentAction: 'edit', section: 'entries', area: 'content', bleed: true } },
		// A new entry opens in the editor, unsaved, until its first save
		// writes it (D-336).
		{ path: '/entries/new', name: 'entry-new', component: EditorView, meta: { title: 'New Entry', contentAction: 'create', section: 'entries', area: 'content', bleed: true } },
		// An entry without a handle is edited at its source path, which
		// also still works for the rest (the editor moves to the handle).
		// An entry in the trash, to look at before restoring it (D-276), by
		// its id (D-484).
		{ path: '/trash/:id([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})', name: 'trashed', component: TrashedView, meta: { title: 'In the Trash', contentAction: 'delete', section: 'entries', area: 'content' } },
		// Drafts are a tab on each type's list now (D-236).
		{ path: '/drafts', redirect: { name: 'dashboard' } },
		// Dated entries by month (D-368); `?month=YYYY-MM`.
		{ path: '/health', name: 'health', component: SiteHealthView, meta: { title: 'Site Health', capability: 'site.health', area: 'home' } },
		{ path: '/health/:area(content)/:check(files|ids|terms|parents|refs|taxonomies|types|folders|names)', name: 'health-check', component: HealthView, props: true, meta: { title: 'Site Health', capability: 'site.health', area: 'home', parent: 'health' } },
		{ path: '/health/:area(media)/:check(files|ids|sizes)', name: 'health-media-check', component: HealthView, props: true, meta: { title: 'Site Health', capability: 'site.health', area: 'home', parent: 'health' } },
		{ path: '/health/:area(content|media)', redirect: (to) => ({ name: to.params.area === 'media' ? 'health-media-check' : 'health-check', params: { area: to.params.area, check: 'files' } }) },
		{ path: '/tools', name: 'tools', component: ToolsView, meta: { title: 'Tools', area: 'home' } },
		// A library file's screen is at its path under `user/media` (D-251).
		{ path: '/media', name: 'media', component: MediaView, meta: { title: 'Media', anyCapability: MEDIA_CAPABILITIES, area: 'content' } },
		{ path: '/media/:path+', name: 'media-file', component: MediaFileView, meta: { title: 'Media', anyCapability: MEDIA_CAPABILITIES, area: 'content', parent: 'media' } },
		{ path: '/types', name: 'types', component: TypesView, meta: { title: 'Content Types', capability: 'site.settings', area: 'config' } },
		{ path: '/types/new', name: 'type-new', component: NewTypeView, meta: { title: 'New Content Type', capability: 'site.settings', area: 'config', parent: 'types' } },
		{ path: '/types/:name', name: 'content-type', component: TypeView, meta: { title: 'Content Type', capability: 'site.settings', area: 'config', parent: 'types' } },
		{ path: '/relationships', name: 'relations', component: RelationsView, meta: { title: 'Relationships', capability: 'site.settings', area: 'config' } },
		{ path: '/relationships/new', name: 'relation-new', component: RelationView, meta: { title: 'New Relationship', capability: 'site.settings', area: 'config', parent: 'relations', wide: true } },
		{ path: '/relationships/:name([a-z0-9_]+)', name: 'relation', component: RelationView, meta: { title: 'Relationship', capability: 'site.settings', area: 'config', parent: 'relations', wide: true } },
		{ path: '/menus', name: 'menus', component: MenusView, meta: { title: 'Menus', capability: 'menus.edit', area: 'config' } },
		{ path: '/menus/:name([a-z0-9][a-z0-9_-]*)', name: 'menu', component: MenuView, meta: { title: 'Menu', capability: 'menus.edit', area: 'config', parent: 'menus' } },
		{ path: '/fields', name: 'fields', component: FieldSetsView, meta: { title: 'Fields', capability: 'site.settings', area: 'config' } },
		{ path: '/fields/new', name: 'field-set-new', component: NewFieldSetView, meta: { title: 'New Field Set', capability: 'site.settings', area: 'config', parent: 'fields' } },
		{ path: '/fields/:name', name: 'field-set', component: FieldSetView, meta: { title: 'Field Set', capability: 'site.settings', area: 'config', parent: 'fields' } },
		// The direction's Appearance, named Themes (D-327).
		{ path: '/themes', name: 'themes', component: ThemesView, meta: { title: 'Themes', capability: 'extensions.themes.view', area: 'extend' } },
		{ path: '/themes/:vendor/:name', name: 'theme', component: ThemeView, meta: { title: 'Theme', capability: 'extensions.themes.view', area: 'extend', parent: 'themes' } },
		// Settings is four screens (D-325); the view titles each.
		{ path: '/settings', redirect: { name: 'settings', params: { screen: 'general' } } },
		// The site's redirects (D-686), listed under Settings.
		{ path: '/redirects', name: 'redirects', component: RedirectsView, meta: { title: 'Redirects', capability: 'site.redirects', area: 'config' } },
		{ path: '/settings/:screen(general|reading|writing|media|search|ai|system)', name: 'settings', component: SettingsView, props: true, meta: { title: 'Settings', capability: 'site.settings', area: 'config', wide: true } },
		{ path: '/plugins', name: 'plugins', component: PluginsView, meta: { title: 'Plugins', capability: 'extensions.plugins.view', area: 'extend' } },
		{ path: '/plugins/:vendor/:name', name: 'plugin', component: PluginView, meta: { title: 'Plugin', capability: 'extensions.plugins.view', area: 'extend', parent: 'plugins' } },
		{ path: '/extensions', redirect: { name: 'plugins' } },
		{ path: '/icon-packs', name: 'icon-packs', component: IconPacksView, meta: { title: 'Icon Packs', capability: 'extensions.icon-packs.view', area: 'extend' } },
		// The core set has a details screen too, though it isn't a pack (D-385).
		{ path: '/icon-packs/core', name: 'icon-pack-core', component: IconPackView, meta: { title: 'Core', capability: 'extensions.icon-packs.view', area: 'extend', parent: 'icon-packs' } },
		{ path: '/icon-packs/:vendor/:name', name: 'icon-pack', component: IconPackView, meta: { title: 'Icon Pack', capability: 'extensions.icon-packs.view', area: 'extend', parent: 'icon-packs' } },
		// People, its own section (D-249, D-326): each list, then a screen
		// per item (`meta.parent` marks the list in the navigation).
		// Accounts and profiles are two lists (D-353): who can sign in,
		// and who's credited. Profiles are their type's entry list, with a
		// screen of their own per profile.
		{ path: '/accounts', name: 'accounts', component: AccountsView, meta: { title: 'Accounts', capability: 'accounts.view', area: 'people' } },
		{ path: '/people', redirect: () => can('accounts.view') ? { name: 'accounts' } : { name: 'profile' } },
		{ path: '/profiles/:slug', name: 'profile-detail', component: ProfileDetailView, meta: { title: 'Profile', contentAction: 'edit', area: 'people', parent: 'profiles' } },
		// New comes before the item it would otherwise be taken for; the
		// admin makes no account or role named "new" (D-312).
		{ path: '/accounts/new', name: 'account-new', component: NewAccountView, meta: { title: 'New Account', capability: 'accounts.create', area: 'people', parent: 'accounts' } },
		// An account's screen; your own is Your Account, which any account
		// may open (D-371), while others' need `accounts.view`.
		{ path: '/accounts/:username', name: 'account', component: AccountView, meta: { title: 'Account', area: 'people', parent: 'accounts' }, beforeEnter: (to) => to.params.username === session.account?.username || can('accounts.view') ? true : { name: 'dashboard' } },
		{ path: '/roles', name: 'roles', component: RolesView, meta: { title: 'Roles', capability: 'accounts.view', area: 'people' } },
		{ path: '/roles/new', name: 'role-new', component: NewRoleView, meta: { title: 'New Role', capability: 'roles.manage', area: 'people', parent: 'roles' } },
		{ path: '/roles/:name', name: 'role', component: RoleView, meta: { title: 'Role', capability: 'accounts.view', area: 'people', parent: 'roles' } },
		// Your Account is your own account's address (D-371); `/profile`
		// and the `profile` name lead there.
		{ path: '/profile', name: 'profile', component: AccountView, meta: { title: 'Your Account', area: 'people' }, beforeEnter: () => ({ name: 'account', params: { username: session.account?.username ?? '' } }) },
		{ path: '/sign-in', name: 'sign-in', component: SignInView, meta: { title: 'Sign In', public: true } },
		// A password link (D-312): anyone with one may open it.
		{ path: '/set-password', name: 'set-password', component: SetPasswordView, meta: { title: 'Choose a Password', public: true } },
		{ path: '/:screen(.*)*', name: 'not-found', component: NotFoundView, meta: { title: 'Not Found' } }
	]
});

router.beforeEach(async (to) => {
	await loadSession();

	if (to.meta.public !== true && session.account === null) {
		return { name: 'sign-in', query: to.fullPath === '/' ? {} : { next: to.fullPath } };
	}

	if (to.name === 'sign-in' && session.account !== null) {
		return { name: 'dashboard' };
	}

	// Screens the account can't use aren't in its navigation either.
	if (typeof to.meta.capability === 'string' && !can(to.meta.capability)) {
		return { name: 'dashboard' };
	}

	// Or a content action (D-359): on the type the address names, or else
	// on any type (the server checks the entry's).
	if (typeof to.meta.contentAction === 'string') {
		const action = to.meta.contentAction as ContentAction;
		const type   = typeof to.params.type === 'string' ? to.params.type : (typeof to.query.type === 'string' ? to.query.type : null);

		if (type === null ? !canAnyType(action) : !canType(type, action)) {
			return { name: 'dashboard' };
		}
	}

	// Or any one of several.
	if (Array.isArray(to.meta.anyCapability) && !to.meta.anyCapability.some((name) => typeof name === 'string' && can(name))) {
		return { name: 'dashboard' };
	}

	return true;
});

/**
 * Whether a navigation keeps the screen that's open: the editor's
 * address moving to the entry it opened as, by its handle, or once a new
 * one is written (D-336).
 */
export function sameScreen(to: RouteLocationNormalized, from: RouteLocationNormalized): boolean {
	const editor = (route: RouteLocationNormalized): boolean => route.name === 'entry' || route.name === 'entry-new';

	return editor(to) && editor(from) && to.name !== from.name;
}

function setTitle(): void {
	const meta  = router.currentRoute.value.meta.title;
	const title = screenTitle.value ?? (typeof meta === 'string' ? meta : 'Admin');

	document.title = `${title} · ${config.site.name}`;
}

router.afterEach((to, from) => {
	if (!sameScreen(to, from) && (to.name !== from.name || to.params.type !== from.params.type)) {
		screenTitle.value = null;
		screenTrail.value = [];
		screenCrumb.value = null;
	}

	lastVisits.set(to.path, to.fullPath);
	setTitle();
});

// A screen that won't load, as when the admin was rebuilt and its file
// is gone, loads the page at the screen's address instead (D-687); the
// first screen a page opens never does, so it can't go round.
router.onError((error: unknown, to, from) => {
	if (from !== START_LOCATION && error instanceof Error && /dynamically imported module|module script failed|preload CSS/i.test(error.message)) {
		window.location.assign(router.resolve(to).href);
	}
});

/**
 * Loads every other screen in the background once the first screen
 * someone signed in to is open and the browser is idle, one at a time
 * and in the order the routes are listed, so moving to a screen doesn't
 * wait on its files (D-690). It pauses while a screen opens, so the one
 * asked for isn't held up. Nothing is fetched on a connection that asks
 * to save data, and a screen that fails here loads as usual when it's
 * opened.
 */
let prefetched = false;
let navigating: Promise<void> | null = null;
let navigated: () => void = () => undefined;

router.beforeEach(() => {
	navigating ??= new Promise((resolve) => {
		navigated = resolve;
	});
});

const settled = (): void => {
	navigated();
	navigating = null;
};

function prefetchScreens(): void {
	const connection = (navigator as Navigator & { connection?: { saveData?: boolean } }).connection;

	if (prefetched || connection?.saveData === true) {
		return;
	}

	prefetched = true;

	const idle = (): Promise<void> => new Promise((resolve) => {
		if ('requestIdleCallback' in window) {
			window.requestIdleCallback(() => resolve(), { timeout: 2000 });
		} else {
			setTimeout(resolve, 200);
		}
	});

	const screens = router.options.routes
		.map((route) => route.component)
		.filter((screen, index, all): screen is () => Promise<unknown> => typeof screen === 'function' && all.indexOf(screen) === index);

	void (async () => {
		for (const screen of screens) {
			await idle();

			while (navigating !== null) {
				await navigating;
				await idle();
			}

			await screen().catch(() => undefined);
		}
	})();
}

router.afterEach((to) => {
	settled();

	if (to.meta.public !== true) {
		prefetchScreens();
	}
});

router.onError(settled);

watch(screenTitle, setTitle);
