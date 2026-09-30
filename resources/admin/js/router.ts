/**
 * The admin's screens. Every screen but sign-in needs an account, and some
 * a capability (`meta.capability`); the server answers the same page for
 * all of them (`ShellController`) and checks every API request itself.
 * Screens that are planned but not built yet (`meta.planned`) say what
 * they'll do (D-241). Each belongs to one of the section rail's areas
 * (`meta.area`: `home`, `content`, or `config`); the editor fills the
 * work area edge to edge (`meta.bleed`).
 */

import { createRouter, createWebHistory } from 'vue-router';
import { watch } from 'vue';
import { config } from './config';
import { screenTitle, type PlannedScreen } from './screen';
import { can, loadSession, session } from './session';
import DashboardView from './views/DashboardView.vue';
import EditorView from './views/EditorView.vue';
import EntriesView from './views/EntriesView.vue';
import HealthView from './views/HealthView.vue';
import NewEntryView from './views/NewEntryView.vue';
import NotFoundView from './views/NotFoundView.vue';
import PlannedView from './views/PlannedView.vue';
import ProfileView from './views/ProfileView.vue';
import AccountView from './views/AccountView.vue';
import AccountsView from './views/AccountsView.vue';
import RoleView from './views/RoleView.vue';
import RolesView from './views/RolesView.vue';
import TypeView from './views/TypeView.vue';
import MediaFileView from './views/MediaFileView.vue';
import MediaView from './views/MediaView.vue';
import TrashedView from './views/TrashedView.vue';
import TypesView from './views/TypesView.vue';
import SignInView from './views/SignInView.vue';

const planned: Record<string, { title: string; capability: string; area: 'content' | 'config'; planned: PlannedScreen }> = {
	appearance: {
		title: 'Appearance',
		capability: 'site.settings',
		area: 'config',
		planned: {
			icon: 'paintbrush',
			hint: 'The theme visitors see. How the admin looks is set on Your profile.',
			next: "Choose the site's theme and adjust its settings.",
			today: 'Until then, use `bin/blush theme:list` and `theme:activate`, and adjust settings in `user/data/theme.json`.'
		}
	},
	extensions: {
		title: 'Extensions',
		capability: 'site.settings',
		area: 'config',
		planned: {
			icon: 'plug',
			hint: 'Code that adds content types, components, icons, and actions',
			next: 'List the installed extensions and what each one adds to the site.',
			today: 'Until then, extensions live in `user/extensions` or are installed with Composer.'
		}
	},
	settings: {
		title: 'Settings',
		capability: 'site.settings',
		area: 'config',
		planned: {
			icon: 'settings',
			hint: 'Site-wide configuration',
			next: "Change the site's name, addresses, and other settings from the browser.",
			today: 'Until then, settings live in `config/` and `.env`.'
		}
	}
};

export const router = createRouter({
	history: createWebHistory(config.base),
	routes: [
		{ path: '/', name: 'dashboard', component: DashboardView, meta: { title: 'Dashboard', area: 'home' } },
		// Each type has its own list; there's no list of every type (D-240).
		{ path: '/entries', redirect: { name: 'dashboard' } },
		{ path: '/content/:type', name: 'type', component: EntriesView, meta: { title: 'Entries', capability: 'content.edit', section: 'entries', area: 'content' } },
		// An entry is edited at its handle, its type and key (D-253); a
		// page's key spans segments.
		{ path: '/content/:type/:key+', name: 'entry', component: EditorView, meta: { title: 'Edit Entry', capability: 'content.edit', section: 'entries', area: 'content', bleed: true } },
		{ path: '/entries/new', name: 'entry-new', component: NewEntryView, meta: { title: 'New Entry', capability: 'content.create', section: 'entries', area: 'content' } },
		// An entry without a handle is edited at its source path, which
		// also still works for the rest (the editor moves to the handle).
		{ path: '/entries/:id+', name: 'entry-file', component: EditorView, meta: { title: 'Edit Entry', capability: 'content.edit', section: 'entries', area: 'content', bleed: true } },
		// A trashed entry, to look at before restoring it (D-276), by the
		// trash's id for it.
		{ path: '/trash/:id+', name: 'trashed', component: TrashedView, meta: { title: 'In the Trash', capability: 'content.delete', section: 'entries', area: 'content' } },
		// Drafts are a tab on each type's list now (D-236).
		{ path: '/drafts', redirect: { name: 'dashboard' } },
		{ path: '/health', name: 'health', component: HealthView, meta: { title: 'Content Health', capability: 'content.edit.others', area: 'home' } },
		...Object.entries(planned).map(([name, meta]) => ({ path: `/${name}`, name, component: PlannedView, meta })),
		// A library file's screen is at its path under `user/media` (D-251).
		{ path: '/media', name: 'media', component: MediaView, meta: { title: 'Media', capability: 'media.upload', area: 'content' } },
		{ path: '/media/:path+', name: 'media-file', component: MediaFileView, meta: { title: 'Media', capability: 'media.upload', area: 'content', parent: 'media' } },
		{ path: '/types', name: 'types', component: TypesView, meta: { title: 'Content Types', capability: 'site.settings', area: 'config' } },
		{ path: '/types/:name', name: 'content-type', component: TypeView, meta: { title: 'Content Type', capability: 'site.settings', area: 'config', parent: 'types' } },
		// People (D-249): each list, then a screen per item (`meta.parent`
		// marks the list in the navigation).
		{ path: '/accounts', name: 'accounts', component: AccountsView, meta: { title: 'Accounts', capability: 'accounts.manage', area: 'config' } },
		{ path: '/accounts/:username', name: 'account', component: AccountView, meta: { title: 'Account', capability: 'accounts.manage', area: 'config', parent: 'accounts' } },
		{ path: '/roles', name: 'roles', component: RolesView, meta: { title: 'Roles', capability: 'accounts.manage', area: 'config' } },
		{ path: '/roles/:name', name: 'role', component: RoleView, meta: { title: 'Role', capability: 'accounts.manage', area: 'config', parent: 'roles' } },
		{ path: '/profile', name: 'profile', component: ProfileView, meta: { title: 'Your Profile', area: 'config' } },
		{ path: '/sign-in', name: 'sign-in', component: SignInView, meta: { title: 'Sign In', public: true } },
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

	return true;
});

function setTitle(): void {
	const meta  = router.currentRoute.value.meta.title;
	const title = screenTitle.value ?? (typeof meta === 'string' ? meta : 'Admin');

	document.title = `${title} · ${config.site.name}`;
}

router.afterEach((to, from) => {
	if (to.name !== from.name || to.params.type !== from.params.type) {
		screenTitle.value = null;
	}

	setTitle();
});

watch(screenTitle, setTitle);
