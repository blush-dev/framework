/**
 * Whether the lists show compact rows (admin.md §6, Space): they ship
 * roomy, and anyone who wants more rows on screen asks for them. The
 * choice is kept in this browser; storage may be off, so reading and
 * writing are allowed to fail, and the lists are then roomy.
 *
 * Themes and Icon Packs keep their Cards or Compact choice the same way
 * (D-565).
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

const VIEW_KEY = 'blush-admin-extension-view';

function storedView(): 'cards' | 'list' {
	try {
		return localStorage.getItem(VIEW_KEY) === 'list' ? 'list' : 'cards';
	} catch {
		return 'cards';
	}
}

// Whether Themes and Icon Packs show cards or a compact list (D-565): one
// choice for both, kept in this browser as the rows' density is.
export const extensionView = ref(storedView());

watch(extensionView, (value) => {
	try {
		if (value === 'list') {
			localStorage.setItem(VIEW_KEY, 'list');
		} else {
			localStorage.removeItem(VIEW_KEY);
		}
	} catch {
		// Kept for this page load only.
	}
});
