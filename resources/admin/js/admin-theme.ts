/**
 * Neutral or Editorial (D-317): the admin's look, an account preference
 * beside the color scheme, saved on the server and followed on any
 * device. The shell page sets it on `<html>` for the signed-in account
 * (`data-admin-theme`, which the tokens read, D-231); this browser
 * remembers the last one in `localStorage`, so the sign-in screen matches
 * too. Storage may be off.
 */

import { readonly, ref } from 'vue';
import { request, type AdminTheme, type Preferences } from './api';
import { config } from './config';

const KEY = 'blush-admin-theme';

function cached(): AdminTheme {
	try {
		return localStorage.getItem(KEY) === 'editorial' ? 'editorial' : 'neutral';
	} catch {
		return 'neutral';
	}
}

const current = ref<AdminTheme>(config.adminTheme ?? cached());

export const adminTheme = readonly(current);

/**
 * Shows a theme and remembers it in this browser. Neutral is the
 * default, so it sets no attribute.
 */
function use(theme: AdminTheme): void {
	current.value = theme;

	if (theme === 'neutral') {
		delete document.documentElement.dataset.adminTheme;
	} else {
		document.documentElement.dataset.adminTheme = theme;
	}

	try {
		if (theme === 'neutral') {
			localStorage.removeItem(KEY);
		} else {
			localStorage.setItem(KEY, theme);
		}
	} catch {
		// Not cached; the account still has it.
	}
}

use(current.value);

/**
 * Follows the signed-in account's theme, as the server sent it.
 */
export function followTheme(preferences: Preferences): void {
	if (preferences.adminTheme !== current.value) {
		use(preferences.adminTheme);
	}
}

/**
 * Changes the account's theme: shown at once, then saved; if saving
 * fails, the old one comes back and the `ApiError` is thrown.
 */
export async function saveAdminTheme(theme: AdminTheme): Promise<void> {
	const before = current.value;

	use(theme);

	try {
		followTheme((await request<{ preferences: Preferences }>('PATCH', '/preferences', { adminTheme: theme })).preferences);
	} catch (caught) {
		use(before);
		throw caught;
	}
}
