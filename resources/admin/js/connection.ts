/**
 * Whether the browser is online, kept current from its `online` and
 * `offline` events, for the offline bar and the editor's save state.
 */

import { ref } from 'vue';

export const online = ref(navigator.onLine);

window.addEventListener('online', () => {
	online.value = true;
});

window.addEventListener('offline', () => {
	online.value = false;
});
