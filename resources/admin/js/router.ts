/**
 * The admin's screens. Every screen but sign-in needs an account, and some
 * a capability (`meta.capability`); the server answers the same page for
 * all of them (`ShellController`) and checks every API request itself.
 */

import { createRouter, createWebHistory } from 'vue-router';
import { config } from './config';
import { can, loadSession, session } from './session';
import DashboardView from './views/DashboardView.vue';
import DraftsView from './views/DraftsView.vue';
import HealthView from './views/HealthView.vue';
import NotFoundView from './views/NotFoundView.vue';
import SignInView from './views/SignInView.vue';

export const router = createRouter({
	history: createWebHistory(config.base),
	routes: [
		{ path: '/', name: 'dashboard', component: DashboardView, meta: { title: 'Dashboard' } },
		{ path: '/drafts', name: 'drafts', component: DraftsView, meta: { title: 'Drafts', capability: 'content.edit' } },
		{ path: '/health', name: 'health', component: HealthView, meta: { title: 'Content health', capability: 'content.edit.others' } },
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

router.afterEach((to) => {
	const title = typeof to.meta.title === 'string' ? to.meta.title : 'Admin';

	document.title = `${title} · ${config.site.name}`;
});
