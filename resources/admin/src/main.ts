/**
 * The Blush admin (D-221): a Vue app over the admin's JSON API.
 */

import { createApp } from 'vue';
import App from './App.vue';
import { router } from './router';
import './style.css';

createApp(App).use(router).mount('#app');
