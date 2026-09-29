/**
 * The admin's screens. Every screen but sign-in needs an account, and some
 * a capability (`meta.capability`); the server answers the same page for
 * all of them (`ShellController`) and checks every API request itself.
 */

import { createRouter, createWebHistory } from 'vue-router';
import { watch } from 'vue';
import { config } from './config';
import { screenTitle } from './screen';
import { can, loadSession, session } from './session';
import DashboardView from './views/DashboardView.vue';
import EditorView from './views/EditorView.vue';
import EntriesView from './views/EntriesView.vue';
import HealthView from './views/HealthView.vue';
import NewEntryView from './views/NewEntryView.vue';
import NotFoundView from './views/NotFoundView.vue';
import ProfileView from './views/ProfileView.vue';
import SignInView from './views/SignInView.vue';

export const router = createRouter({
	history: createWebHistory(config.base),
	routes: [
		{ path: '/', name: 'dashboard', component: DashboardView, meta: { title: 'Dashboard' } },
		// Each type has its own list; there's no list of every type (D-240).
		{ path: '/entries', redirect: { name: 'dashboard' } },
		{ path: '/content/:type', name: 'type', component: EntriesView, meta: { title: 'Entries', capability: 'content.edit', section: 'entries' } },
		{ path: '/entries/new', name: 'entry-new', component: NewEntryView, meta: { title: 'New entry', capability: 'content.create', section: 'entries' } },
		// An entry's id is its source path, so it spans segments.
		{ path: '/entries/:id+', name: 'entry', component: EditorView, meta: { title: 'Edit entry', capability: 'content.edit', section: 'entries' } },
		// Drafts are a tab on each type's list now (D-236).
		{ path: '/drafts', redirect: { name: 'dashboard' } },
		{ path: '/health', name: 'health', component: HealthView, meta: { title: 'Content health', capability: 'content.edit.others' } },
		{ path: '/profile', name: 'profile', component: ProfileView, meta: { title: 'Your profile' } },
		{ path: '/sign-in', name: 'sign-in', component: SignInView, meta: { title: 'Sign in', public: true } },
		{ path: '/:screen(.*)*', name: 'not-found', component: NotFoundView, meta: { title: 'Not found' } }
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
