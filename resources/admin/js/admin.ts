/**
 * The Blush admin (D-221): a Vue app over the admin's JSON API.
 */

import { createApp } from 'vue';
import App from './App.vue';
import './color-scheme';
import { t } from './messages';
import { router } from './router';
import '../css/admin.css';

// A screen loaded on demand from a newer build than the open admin's
// imports that build's admin, which loads the page again rather than
// mounting a second one (D-687).
if (document.querySelector('#app[data-v-app]') !== null) {
	window.location.reload();
} else {
	const app = createApp(App);

	// Text in templates, `{{ t('key', 'Text') }}` (D-831).
	app.config.globalProperties.t = t;
	app.use(router).mount('#app');
}
