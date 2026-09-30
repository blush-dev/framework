/**
 * Whether the lists show compact rows (admin.md §6, Space): they ship
 * roomy, and anyone who wants more rows on screen asks for them. The
 * choice is kept in this browser; storage may be off, so reading and
 * writing are allowed to fail, and the lists are then roomy.
 */

import { ref, watch } from 'vue';

const KEY = 'blush-admin-density';

function stored(): boolean {
	try {
		return localStorage.getItem(KEY) === 'compact';
	} catch {
		return false;
	}
}

export const compact = ref(stored());

watch(compact, (value) => {
	try {
		if (value) {
			localStorage.setItem(KEY, 'compact');
		} else {
			localStorage.removeItem(KEY);
		}
	} catch {
		// Kept for this page load only.
	}
});
