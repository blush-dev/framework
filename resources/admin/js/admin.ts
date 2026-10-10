/**
 * The Blush admin (D-221): a Vue app over the admin's JSON API.
 */

import { createApp } from 'vue';
import App from './App.vue';
import './color-scheme';
import { router } from './router';
import '../css/admin.css';

// A screen loaded on demand from a newer build than the open admin's
// imports that build's admin, which loads the page again rather than
// mounting a second one (D-687).
if (document.querySelector('#app[data-v-app]') !== null) {
	window.location.reload();
} else {
	createApp(App).use(router).mount('#app');
}
