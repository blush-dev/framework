/**
 * The admin's screens. Every screen but sign-in needs an account; the
 * server answers the same page for all of them (`ShellController`).
 */

import { createRouter, createWebHistory } from 'vue-router';
import { config } from './config';
import { loadSession, session } from './session';
import DashboardView from './views/DashboardView.vue';
import NotFoundView from './views/NotFoundView.vue';
import SignInView from './views/SignInView.vue';

export const router = createRouter({
	history: createWebHistory(config.base),
	routes: [
		{ path: '/', name: 'dashboard', component: DashboardView, meta: { title: 'Dashboard' } },
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

	return true;
});

router.afterEach((to) => {
	const title = typeof to.meta.title === 'string' ? to.meta.title : 'Admin';

	document.title = `${title} · ${config.site.name}`;
});
